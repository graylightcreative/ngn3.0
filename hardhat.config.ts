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

task("mint-certificate", "Mints a content ownership NFT certificate")
  .addParam("artist", "The address of the artist")
  .addParam("hash", "The content hash (0x...)")
  .addParam("uri", "The IPFS metadata URI")
  .setAction(async (taskArgs, hre) => {
    const { artist, hash, uri } = taskArgs;
    const contractAddress = process.env.NFT_CONTRACT_ADDRESS;
    
    if (!contractAddress) {
      throw new Error("NFT_CONTRACT_ADDRESS not found in .env");
    }

    const NFT = await hre.ethers.getContractAt("ContentCertificateNFT", contractAddress);
    
    console.log(`Minting certificate for artist ${artist} with hash ${hash}`);
    const tx = await NFT.mintCertificate(artist, hash, uri);
    const receipt = await tx.wait();
    
    // Find the CertificateMinted event to get the tokenId
    const event = receipt?.logs.find(
      (log: any) => log.fragment && log.fragment.name === 'CertificateMinted'
    );
    
    const tokenId = event ? event.args[1].toString() : null;

    console.log(JSON.stringify({
      success: true,
      tx_hash: tx.hash,
      token_id: tokenId,
      artist,
      content_hash: hash,
      token_uri: uri
    }));
  });

const config: HardhatUserConfig = {
  solidity: "0.8.20",
  networks: {
    amoy: {
      url: process.env.POLYGON_AMOY_RPC || "https://rpc-amoy.polygon.technology",
      accounts: process.env.PRIVATE_KEY ? [process.env.PRIVATE_KEY] : [],
      chainId: 80002,
    },
  },
};

export default config;
