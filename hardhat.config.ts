import { HardhatUserConfig, task } from "hardhat/config";
import "@nomicfoundation/hardhat-toolbox";
import "dotenv/config";

task("anchor", "Anchors a Merkle root to the blockchain")
  .addParam("root", "The Merkle root to anchor")
  .setAction(async (taskArgs, hre) => {
    const merkleRoot = taskArgs.root;
    const contractAddress = process.env.CONTRACT_ADDRESS;
    
    if (!contractAddress) {
      throw new Error("CONTRACT_ADDRESS not found in .env");
    }

    const ContentLedgerAnchor = await hre.ethers.getContractAt("ContentLedgerAnchor", contractAddress);
    
    console.log(`Anchoring Merkle root: ${merkleRoot}`);
    const tx = await ContentLedgerAnchor.anchor(merkleRoot);
    const receipt = await tx.wait();
    
    console.log(JSON.stringify({
      success: true,
      tx_hash: tx.hash,
      block_number: receipt?.blockNumber,
      merkle_root: merkleRoot
    }));
  });

const config: HardhatUserConfig = {
  solidity: "0.8.19",
  networks: {
    amoy: {
      url: process.env.POLYGON_AMOY_RPC || "https://rpc-amoy.polygon.technology",
      accounts: process.env.PRIVATE_KEY ? [process.env.PRIVATE_KEY] : [],
      chainId: 80002,
    },
  },
};

export default config;
