import { ethers } from "hardhat";

async function main() {
  console.log("🚀 NGN 2.0.3 - ContentCertificateNFT Deployment");
  console.log("=".repeat(50));

  const [deployer] = await ethers.getSigners();
  console.log(`📍 Deploying with account: ${deployer.address}`);

  const balance = await ethers.provider.getBalance(deployer.address);
  console.log(`💰 Account balance: ${ethers.formatEther(balance)} POL\n`);

  console.log("📦 Deploying ContentCertificateNFT contract...");
  const ContentCertificateNFT = await ethers.getContractFactory("ContentCertificateNFT");
  const contract = await ContentCertificateNFT.deploy();

  await contract.waitForDeployment();
  const contractAddress = await contract.getAddress();

  console.log(`✅ Contract deployed to: ${contractAddress}\n`);

  const name = await contract.name();
  const symbol = await contract.symbol();
  console.log(`🏷️ NFT Name: ${name}`);
  console.log(`🏷️ NFT Symbol: ${symbol}\n`);

  const deploymentInfo = {
    contractAddress,
    deployerAddress: deployer.address,
    network: "Polygon Amoy",
    chainId: 80002,
    deployedAt: new Date().toISOString(),
  };

  console.log("📝 Deployment Summary:");
  console.log(JSON.stringify(deploymentInfo, null, 2));

  console.log("\n✅ Deployment successful!");
  console.log(`   NFT_CONTRACT_ADDRESS=${contractAddress}`);
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });