import { expect } from "chai";
import { ethers } from "hardhat";
import { ContentLedgerAnchor } from "../typechain-types";

describe("ContentLedgerAnchor", function () {
  let contract: ContentLedgerAnchor;
  let owner: any;
  let addr1: any;

  const merkleRoot1 = "0x" + "1".padStart(64, "0");
  const merkleRoot2 = "0x" + "2".padStart(64, "0");
  const contentHash = "0x" + "a".padStart(64, "0");

  beforeEach(async function () {
    [owner, addr1] = await ethers.getSigners();

    const ContentLedgerAnchorFactory = await ethers.getContractFactory("ContentLedgerAnchor");
    contract = await ContentLedgerAnchorFactory.deploy();
    await contract.waitForDeployment();
  });

  describe("Deployment", function () {
    it("Should set the right admin", async function () {
      expect(await contract.admin()).to.equal(owner.address);
    });

    it("Should start with 0 anchors", async function () {
      expect(await contract.totalAnchors()).to.equal(0);
    });
  });

  describe("Anchor Submission", function () {
    it("Should submit a merkle root", async function () {
      const tx = await contract.anchor(merkleRoot1);
      await tx.wait();

      expect(await contract.isAnchored(merkleRoot1)).to.be.true;
      expect(await contract.totalAnchors()).to.equal(1);
    });

    it("Should emit AnchorSubmitted event", async function () {
      const tx = await contract.anchor(merkleRoot1);
      const receipt = await tx.wait();
      const block = await ethers.provider.getBlock(receipt!.blockNumber);

      await expect(tx)
        .to.emit(contract, "AnchorSubmitted")
        .withArgs(merkleRoot1, block!.timestamp, block!.number);
    });

    it("Should store timestamp", async function () {
      const blockBefore = await ethers.provider.getBlock("latest");
      await contract.anchor(merkleRoot1);
      const blockAfter = await ethers.provider.getBlock("latest");

      const timestamp = await contract.getTimestamp(merkleRoot1);
      expect(timestamp).to.be.gte(blockBefore?.timestamp || 0);
      expect(timestamp).to.be.lte(blockAfter?.timestamp || 0);
    });

    it("Should prevent duplicate anchors", async function () {
      await contract.anchor(merkleRoot1);
      await expect(contract.anchor(merkleRoot1)).to.be.revertedWith("Root already anchored");
    });

    it("Should reject zero merkle root", async function () {
      const zeroRoot = "0x" + "0".padStart(64, "0");
      await expect(contract.anchor(zeroRoot)).to.be.revertedWith("Invalid merkle root");
    });

    it("Should reject non-admin submissions", async function () {
      await expect(contract.connect(addr1).anchor(merkleRoot1)).to.be.revertedWith(
        "Only admin can call this function"
      );
    });
  });

  describe("Anchor Retrieval", function () {
    beforeEach(async function () {
      await contract.anchor(merkleRoot1);
      await contract.anchor(merkleRoot2);
    });

    it("Should return correct anchor count", async function () {
      expect(await contract.getAnchorCount()).to.equal(2);
    });

    it("Should check if merkle root is anchored", async function () {
      expect(await contract.isAnchored(merkleRoot1)).to.be.true;
      expect(await contract.isAnchored(merkleRoot2)).to.be.true;

      const notAnchored = "0x" + "3".padStart(64, "0");
      expect(await contract.isAnchored(notAnchored)).to.be.false;
    });

    it("Should return timestamp for anchored root", async function () {
      const timestamp = await contract.getTimestamp(merkleRoot1);
      expect(timestamp).to.be.greaterThan(0);
    });

    it("Should return block number for anchored root", async function () {
      const blockNum = await contract.getBlockNumber(merkleRoot1);
      expect(blockNum).to.be.greaterThan(0);
    });

    it("Should reject timestamp query for unanchored root", async function () {
      const notAnchored = "0x" + "3".padStart(64, "0");
      await expect(contract.getTimestamp(notAnchored)).to.be.revertedWith("Merkle root not found");
    });
  });

  describe("Admin Transfer", function () {
    it("Should transfer admin rights", async function () {
      await contract.transferAdmin(addr1.address);
      expect(await contract.admin()).to.equal(addr1.address);
    });

    it("Should prevent non-admin from transferring admin", async function () {
      await expect(contract.connect(addr1).transferAdmin(addr1.address)).to.be.revertedWith(
        "Only admin can call this function"
      );
    });

    it("Should prevent transferring to zero address", async function () {
      await expect(contract.transferAdmin("0x0000000000000000000000000000000000000000")).to.be.revertedWith(
        "Invalid address"
      );
    });

    it("New admin should be able to submit anchors", async function () {
      await contract.transferAdmin(addr1.address);
      await contract.connect(addr1).anchor(merkleRoot1);
      expect(await contract.isAnchored(merkleRoot1)).to.be.true;
    });
  });

  describe("Content Verification", function () {
    it("Should verify content when anchors exist", async function () {
      await contract.anchor(merkleRoot1);
      expect(await contract.verify(contentHash)).to.be.true;
    });

    it("Should not verify when no anchors exist", async function () {
      expect(await contract.verify(contentHash)).to.be.false;
    });
  });

  describe("Gas Estimation", function () {
    it("Should estimate anchor submission gas", async function () {
      const gasEstimate = await contract.anchor.estimateGas(merkleRoot1);
      console.log("Gas estimate for anchor:", gasEstimate.toString());
      expect(gasEstimate).to.be.greaterThan(0);
    });
  });
});
