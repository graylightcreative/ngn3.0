# 🔗 Polygon Setup Guide for NGN 2.0.3

**Purpose:** Complete setup for blockchain development on Polygon testnet
**Target:** Smart contract deployment to Polygon Mumbai
**Duration:** 30 minutes to full setup
**Difficulty:** Beginner-friendly with detailed commands

---

## ⚡ Quick Start (5 minutes)

```bash
# 1. Install Node.js 18+ (if not already installed)
node --version  # Should be v18+
npm --version   # Should be v9+

# 2. Install Hardhat globally
npm install -g hardhat

# 3. Create project directory
mkdir ngn-blockchain
cd ngn-blockchain

# 4. Initialize Hardhat project
npx hardhat init
# Choose: Create TypeScript project

# 5. Install Web3.js
npm install web3.js

# 6. Fund testnet wallet (see below)
```

---

## 🌐 Polygon Networks Explained

### Ethereum Mainnet vs Polygon vs Testnet

| Network | Chain ID | Purpose | Real Money? | Use Case |
|---------|----------|---------|------------|----------|
| **Polygon Mumbai** | 80001 | **Development/Testing** | ❌ No (testnet) | 👉 **START HERE** |
| **Polygon Mainnet** | 137 | Production (real) | ✅ Yes (real money) | Launch later (Week 16) |
| **Ethereum Mainnet** | 1 | Production alternative | ✅ Yes (expensive gas) | Not recommended |
| **Sepolia (Ethereum)** | 11155111 | Ethereum testnet | ❌ No (testnet) | Alternative option |

**DECISION:** Use Polygon Mumbai for development (cheaper, faster)

---

## 📦 Step 1: Install Prerequisites

### Check Node.js Installation
```bash
node --version
npm --version
```

**Required versions:**
- Node.js: v18.0.0 or higher
- npm: v9.0.0 or higher

**If not installed:**
- Download from https://nodejs.org/ (LTS version recommended)
- Install and verify with commands above

### Install Hardhat
```bash
npm install -g hardhat

# Verify installation
hardhat --version
# Should output: hardhat/X.X.X
```

---

## 🏗️ Step 2: Create Hardhat Project

### Initialize Project
```bash
# Create directory
mkdir ngn-blockchain-contracts
cd ngn-blockchain-contracts

# Create package.json
npm init -y

# Install Hardhat (locally in project)
npm install --save-dev hardhat

# Initialize Hardhat project
npx hardhat init
```

**When prompted, select:**
- ✅ "Create a TypeScript project"
- ✅ "Do you want to install the sample project dependencies?" → Yes

### Project Structure Created
```
ngn-blockchain-contracts/
├── contracts/
│   └── Lock.sol (sample - we'll replace)
├── test/
│   └── Lock.ts (sample - we'll replace)
├── hardhat.config.ts
├── package.json
├── tsconfig.json
└── .gitignore
```

---

## 🔐 Step 3: Create Testnet Wallet

### Option A: Use MetaMask (Recommended for Development)

1. **Install MetaMask Browser Extension**
   - Chrome: https://chrome.google.com/webstore → Search "MetaMask"
   - Firefox: https://addons.mozilla.org/firefox → Search "MetaMask"
   - Install and create account

2. **Add Polygon Mumbai Network to MetaMask**
   - Click Network selector (top left) → "Add network"
   - Fill in:
     ```
     Network Name: Polygon Mumbai
     RPC URL: https://rpc-mumbai.maticvigil.com
     Chain ID: 80001
     Currency Symbol: MATIC
     Block Explorer: https://mumbai.polygonscan.com
     ```
   - Save

3. **Get Testnet MATIC Tokens (Free)**
   - Visit: https://faucet.polygon.technology/
   - Select "Polygon Mumbai" from dropdown
   - Enter your MetaMask address
   - Click "Submit"
   - Wait 30 seconds - you'll receive 1 MATIC (free, for testing)

4. **Export Private Key (SECURE!)**
   - MetaMask → Account details → Export private key
   - ⚠️ **NEVER share this key!**
   - Save in `.env` file (see below)

### Option B: Create with ethers/web3 (For Scripting)

```bash
# Install ethers for key generation
npm install ethers

# Create account-generator.js
cat > generate-account.js << 'EOF'
const { ethers } = require('ethers');

// Generate random wallet
const wallet = ethers.Wallet.createRandom();
console.log('Address:', wallet.address);
console.log('Private Key:', wallet.privateKey);
console.log('Mnemonic:', wallet.mnemonic.phrase);

// Save to .env manually
EOF

# Run
node generate-account.js
```

---

## 🛠️ Step 4: Configure Hardhat for Polygon

### Edit `hardhat.config.ts`

Replace the entire file with:

```typescript
import { HardhatUserConfig } from "hardhat/config";
import "@nomicfoundation/hardhat-toolbox";
import "@nomicfoundation/hardhat-ethers";
import "dotenv/config";

const config: HardhatUserConfig = {
  solidity: "0.8.19",
  networks: {
    // Polygon Mumbai Testnet (for development)
    mumbai: {
      url: process.env.POLYGON_MUMBAI_RPC || "https://rpc-mumbai.maticvigil.com",
      accounts: process.env.PRIVATE_KEY_TESTNET ? [process.env.PRIVATE_KEY_TESTNET] : [],
      chainId: 80001,
    },
    // Polygon Mainnet (for production - later)
    polygon: {
      url: process.env.POLYGON_MAINNET_RPC || "https://polygon-rpc.com/",
      accounts: process.env.PRIVATE_KEY_MAINNET ? [process.env.PRIVATE_KEY_MAINNET] : [],
      chainId: 137,
    },
    // Local development network (for testing locally)
    hardhat: {
      chainId: 31337,
    },
  },
  etherscan: {
    apiKey: process.env.ETHERSCAN_API_KEY || "",
  },
};

export default config;
```

### Install Required Dependencies

```bash
npm install --save-dev @nomicfoundation/hardhat-toolbox
npm install --save-dev @nomicfoundation/hardhat-ethers
npm install ethers
npm install dotenv
```

---

## 🔑 Step 5: Create `.env` File

### Create `.env` in project root

```bash
cat > .env << 'EOF'
# Polygon RPC URLs
POLYGON_MUMBAI_RPC=https://rpc-mumbai.maticvigil.com
POLYGON_MAINNET_RPC=https://polygon-rpc.com/

# Test Wallet (from MetaMask export)
PRIVATE_KEY_TESTNET=0x... (paste your private key here)

# Production Wallet (for later - leave empty for now)
PRIVATE_KEY_MAINNET=0x... (add before mainnet deployment)

# Optional: For contract verification on PolygonScan
ETHERSCAN_API_KEY=your_api_key_here
EOF
```

### Update `.gitignore`

Add these lines to `.gitignore`:
```
.env
.env.local
.env.*.local
*.log
```

⚠️ **CRITICAL:** Never commit `.env` to git!

---

## 🌍 Step 6: Verify Network Connection

### Test Polygon Mumbai Connection

```bash
# Create test script
cat > test-connection.js << 'EOF'
const ethers = require('ethers');

const testConnection = async () => {
  // Connect to Mumbai testnet
  const provider = new ethers.JsonRpcProvider(
    'https://rpc-mumbai.maticvigil.com'
  );

  try {
    const blockNumber = await provider.getBlockNumber();
    const network = await provider.getNetwork();

    console.log('✅ Connected to Polygon Mumbai!');
    console.log('Chain ID:', network.chainId);
    console.log('Chain Name:', network.name);
    console.log('Latest Block:', blockNumber);
  } catch (error) {
    console.error('❌ Connection failed:', error.message);
  }
};

testConnection();
EOF

# Run
node test-connection.js
```

**Expected output:**
```
✅ Connected to Polygon Mumbai!
Chain ID: 80001
Chain Name: maticmum
Latest Block: 38964521
```

---

## 💰 Step 7: Check Wallet Balance

### Verify Testnet MATIC Balance

```bash
cat > check-balance.js << 'EOF'
const ethers = require('ethers');
require('dotenv').config();

const checkBalance = async () => {
  const provider = new ethers.JsonRpcProvider(
    process.env.POLYGON_MUMBAI_RPC
  );

  const wallet = new ethers.Wallet(
    process.env.PRIVATE_KEY_TESTNET,
    provider
  );

  const balance = await provider.getBalance(wallet.address);
  const balanceInMatic = ethers.formatEther(balance);

  console.log('📍 Wallet Address:', wallet.address);
  console.log('💰 Balance:', balanceInMatic, 'MATIC');
};

checkBalance().catch(console.error);
EOF

# Run
node check-balance.js
```

**Expected output:**
```
📍 Wallet Address: 0x1234...5678
💰 Balance: 1.0 MATIC
```

If balance is 0, use faucet again:
https://faucet.polygon.technology/

---

## 🚀 Step 8: Deploy First Contract (Test)

### Create Simple Test Contract

```bash
# Replace contracts/Lock.sol with:
cat > contracts/HelloPolygon.sol << 'EOF'
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

contract HelloPolygon {
    string public message = "Hello Polygon!";

    function setMessage(string memory newMessage) public {
        message = newMessage;
    }

    function getMessage() public view returns (string memory) {
        return message;
    }
}
EOF
```

### Create Deployment Script

```bash
cat > scripts/deploy.ts << 'EOF'
import { ethers } from "hardhat";

async function main() {
  console.log("🚀 Deploying HelloPolygon contract...");

  const HelloPolygon = await ethers.getContractFactory("HelloPolygon");
  const contract = await HelloPolygon.deploy();
  await contract.waitForDeployment();

  const address = await contract.getAddress();
  console.log("✅ HelloPolygon deployed to:", address);
  console.log("📝 Save this address for verification!");
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
EOF
```

### Deploy to Mumbai Testnet

```bash
npx hardhat run scripts/deploy.ts --network mumbai
```

**Expected output:**
```
🚀 Deploying HelloPolygon contract...
✅ HelloPolygon deployed to: 0xaBCD...EF01
📝 Save this address for verification!
```

### Verify on PolygonScan

1. Go to: https://mumbai.polygonscan.com/
2. Search your contract address (0xaBCD...EF01)
3. You should see:
   - Contract creator
   - Transaction hash
   - Deployment block
   - Contract ABI

---

## 📊 Network Details for Reference

### Polygon Mumbai Testnet
```
Chain ID: 80001
RPC: https://rpc-mumbai.maticvigil.com
Explorer: https://mumbai.polygonscan.com
Gas Token: MATIC
Average Block Time: ~2 seconds
Finality: ~128 blocks (~4 minutes)
```

### Polygon Mainnet
```
Chain ID: 137
RPC: https://polygon-rpc.com/
Explorer: https://polygonscan.com
Gas Token: MATIC
Average Block Time: ~2 seconds
```

---

## 🐛 Troubleshooting

### Problem: "Cannot find module 'dotenv'"
```bash
npm install dotenv
```

### Problem: "Invalid private key"
- Check `.env` file has correct private key format (0x...)
- Make sure no extra spaces or quotes

### Problem: "Network error: connection timeout"
- RPC might be overloaded
- Try alternative RPC:
  ```
  https://polygon-mumbai-rpc.allthatnode.com:8545
  https://rpc.ankr.com/polygon_mumbai
  ```

### Problem: "Insufficient balance for gas"
- Need more testnet MATIC
- Use faucet: https://faucet.polygon.technology/
- Or request from team

### Problem: "Contract deployment failed"
- Check Solidity version in hardhat.config.ts
- Check contract syntax errors
- Try compiling first:
  ```bash
  npx hardhat compile
  ```

---

## ✅ Verification Checklist

- [ ] Node.js v18+ installed
- [ ] Hardhat project initialized
- [ ] `.env` file created with private key
- [ ] `hardhat.config.ts` configured for Polygon
- [ ] MetaMask wallet created & funded with testnet MATIC
- [ ] Connection to Mumbai testnet verified
- [ ] Wallet balance checked (shows MATIC)
- [ ] Test contract deployed successfully
- [ ] Contract visible on PolygonScan

---

## 🎯 Next Steps

1. ✅ **Setup complete** - You have working Polygon testnet environment
2. → **Create ContentLedgerAnchor.sol** smart contract (see Web3.js guide)
3. → **Write unit tests** using Hardhat test framework
4. → **Deploy contract** to Mumbai
5. → **Integrate Web3.js** from PHP layer

---

## 📚 Resources

**Official Docs:**
- Polygon: https://docs.polygon.technology/
- Hardhat: https://hardhat.org/docs
- Solidity: https://docs.soliditylang.org/

**Testnet Faucets:**
- Polygon Mumbai: https://faucet.polygon.technology/
- Alternative: https://www.allthatnode.com/faucet/polygon.dsn

**Block Explorers:**
- Mumbai: https://mumbai.polygonscan.com
- Mainnet: https://polygonscan.com

**Community:**
- Polygon Discord: https://discord.gg/polygon
- Ethereum Stack Exchange: https://ethereum.stackexchange.com/

---

**Status:** ✅ READY TO START DEVELOPMENT

All setup complete. You now have:
- ✅ Hardhat development environment
- ✅ Polygon Mumbai testnet configured
- ✅ Test wallet with MATIC tokens
- ✅ Connection verified

**Next:** Move to Web3.js-Setup-Guide.md for PHP integration layer setup.

---

Created: February 9, 2026
Duration to complete: 30 minutes
Difficulty: Beginner-friendly
Next step: Web3.js integration
