import { ethers } from "hardhat";
import * as dotenv from "dotenv";

dotenv.config();

async function main() {
  const merkleRoot = process.argv[2];
  if (!merkleRoot || !merkleRoot.startsWith("0x")) {
    console.error("Error: Please provide a valid Merkle root (0x...)");
    process.exit(1);
  }

  const contractAddress = process.env.CONTRACT_ADDRESS;
  if (!contractAddress) {
    console.error("Error: CONTRACT_ADDRESS not found in .env");
    process.exit(1);
  }

  const ContentLedgerAnchor = await ethers.getContractAt("ContentLedgerAnchor", contractAddress);
  
  console.log(`Anchoring Merkle root: ${merkleRoot}`);
  
  try {
    const tx = await ContentLedgerAnchor.anchor(merkleRoot);
    console.log(`Transaction sent: ${tx.hash}`);
    
    const receipt = await tx.wait();
    console.log(`Transaction confirmed in block ${receipt?.blockNumber}`);
    
    // Output JSON for PHP to parse
    console.log(JSON.stringify({
      success: true,
      tx_hash: tx.hash,
      block_number: receipt?.blockNumber,
      merkle_root: merkleRoot
    }));
  } catch (error: any) {
    console.error(`Error anchoring: ${error.message}`);
    process.exit(1);
  }
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
