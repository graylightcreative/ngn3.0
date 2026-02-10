import { ethers } from "hardhat";

async function main() {
  console.log("🚀 NGN 2.0.3 - ContentLedgerAnchor Deployment");
  console.log("=".repeat(50));

  // Get deployer account
  const [deployer] = await ethers.getSigners();
  console.log(`📍 Deploying with account: ${deployer.address}`);

  // Get account balance
  const balance = await ethers.provider.getBalance(deployer.address);
  const balanceInMatic = ethers.formatEther(balance);
  console.log(`💰 Account balance: ${balanceInMatic} MATIC\n`);

  // Deploy contract
  console.log("📦 Deploying ContentLedgerAnchor contract...");
  const ContentLedgerAnchor = await ethers.getContractFactory("ContentLedgerAnchor");
  const contract = await ContentLedgerAnchor.deploy();

  await contract.waitForDeployment();
  const contractAddress = await contract.getAddress();

  console.log(`✅ Contract deployed to: ${contractAddress}\n`);

  // Get deployment details
  const deploymentTx = contract.deploymentTransaction();
  if (deploymentTx) {
    const receipt = await deploymentTx.wait();
    console.log(`📊 Deployment Details:`);
    console.log(`   Transaction Hash: ${receipt?.transactionHash}`);
    console.log(`   Block Number: ${receipt?.blockNumber}`);
    console.log(`   Gas Used: ${receipt?.gasUsed}`);
    console.log(`   Gas Price: ${ethers.formatUnits(receipt?.gasPrice || 0, 'gwei')} gwei\n`);
  }

  // Verify admin
  const admin = await contract.admin();
  console.log(`🔐 Admin Address: ${admin}\n`);

  // Save deployment info
  const deploymentInfo = {
    contractAddress,
    deployerAddress: deployer.address,
    admin,
    network: "Polygon Amoy",
    chainId: 80002,
    deployedAt: new Date().toISOString(),
  };

  console.log("📝 Deployment Summary:");
  console.log(JSON.stringify(deploymentInfo, null, 2));

  console.log("\n✅ Deployment successful!");
  console.log("📋 Save the contract address for verification:");
  console.log(`   CONTRACT_ADDRESS=${contractAddress}`);
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
