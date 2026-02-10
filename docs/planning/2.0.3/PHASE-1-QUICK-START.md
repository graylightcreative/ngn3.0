# 🚀 Phase 1 Week 1 - Quick Start Guide

**Objective:** Get smart contract running on Polygon Mumbai testnet
**Duration:** Day 1 (5 Days total in Week 1)
**Status:** Ready to Execute

---

## ⚡ 30-Second Setup

```bash
# 1. Install dependencies
npm install

# 2. Compile contract
npx hardhat compile

# 3. Run tests
npx hardhat test

# 4. Deploy to testnet
npx hardhat run scripts/deploy.ts --network mumbai
```

---

## 📋 Prerequisites Checklist

Before you start, verify:

- [ ] Node.js v18+ installed: `node --version`
- [ ] npm v9+ installed: `npm --version`
- [ ] Hardhat installed: `npx hardhat --version`
- [ ] Polygon Mumbai testnet wallet created with MATIC tokens (see POLYGON-SETUP-GUIDE.md)
- [ ] `.env` file created with:
  - `POLYGON_MUMBAI_RPC=https://rpc-mumbai.maticvigil.com`
  - `PRIVATE_KEY_TESTNET=0x...` (your testnet private key)
- [ ] Project directory initialized: `/path/to/ngn-blockchain-contracts/`

---

## 🏁 Day 1 Execution Plan

### Morning (4 hours)

#### 1. Project Setup (30 min)
```bash
# Create project directory
mkdir ngn-blockchain-contracts
cd ngn-blockchain-contracts

# Initialize npm
npm init -y

# Install Hardhat
npm install --save-dev hardhat @nomicfoundation/hardhat-toolbox

# Initialize Hardhat
npx hardhat init
# → Select: Create TypeScript project
# → Install dependencies: Yes
```

#### 2. Install Additional Dependencies (15 min)
```bash
npm install --save-dev @nomicfoundation/hardhat-ethers ethers
npm install dotenv
```

#### 3. Create Smart Contract (45 min)
```bash
# Copy ContentLedgerAnchor.sol to contracts/
# File: contracts/ContentLedgerAnchor.sol
```

#### 4. Compile Contract (15 min)
```bash
npx hardhat compile
```

**Expected output:**
```
Compiling 1 file with 0.8.19
ContentLedgerAnchor compiled successfully
```

### Afternoon (4 hours)

#### 5. Setup Hardhat Config (30 min)
Edit `hardhat.config.ts`:
```typescript
import { HardhatUserConfig } from "hardhat/config";
import "@nomicfoundation/hardhat-toolbox";
import "dotenv/config";

const config: HardhatUserConfig = {
  solidity: "0.8.19",
  networks: {
    mumbai: {
      url: process.env.POLYGON_MUMBAI_RPC || "https://rpc-mumbai.maticvigil.com",
      accounts: process.env.PRIVATE_KEY_TESTNET ? [process.env.PRIVATE_KEY_TESTNET] : [],
      chainId: 80001,
    },
  },
};

export default config;
```

#### 6. Create `.env` File (10 min)
```bash
cat > .env << 'EOF'
POLYGON_MUMBAI_RPC=https://rpc-mumbai.maticvigil.com
PRIVATE_KEY_TESTNET=0x... (paste your testnet private key)
EOF

# Add to .gitignore
echo ".env" >> .gitignore
echo ".env.local" >> .gitignore
```

#### 7. Write Unit Tests (1.5 hours)
```bash
# Copy test/ContentLedgerAnchor.test.ts
```

#### 8. Run Tests (1 hour)
```bash
npx hardhat test
```

**Expected output:**
```
ContentLedgerAnchor
  Deployment
    ✓ Should set the right admin
    ✓ Should start with 0 anchors
  Anchor Submission
    ✓ Should submit a merkle root
    ✓ Should emit AnchorSubmitted event
    ✓ Should store timestamp
    ✓ Should prevent duplicate anchors
    ✓ Should reject zero merkle root
    ✓ Should reject non-admin submissions
  ...
  45 passing (XXs)
```

---

## 🌐 Day 2-4: Testnet Deployment

### Day 2: Fund Wallet & Deploy (2-3 hours)

```bash
# 1. Get testnet MATIC (free faucet)
# Visit: https://faucet.polygon.technology/
# Select: Polygon Mumbai
# Paste your wallet address
# Wait for 1 MATIC to arrive

# 2. Verify balance
node -e "const w=require('ethers'); const p=new w.JsonRpcProvider('https://rpc-mumbai.maticvigil.com'); p.getBalance('0x...YOUR_ADDRESS').then(b=>console.log(w.formatEther(b)))"

# 3. Deploy contract
npx hardhat run scripts/deploy.ts --network mumbai
```

**Expected output:**
```
🚀 NGN 2.0.3 - ContentLedgerAnchor Deployment
==================================================
📍 Deploying with account: 0x...
💰 Account balance: 0.5 MATIC

📦 Deploying ContentLedgerAnchor contract...
✅ Contract deployed to: 0xABC...DEF

📊 Deployment Details:
   Transaction Hash: 0x...
   Block Number: 12345678
   Gas Used: 450000
   Gas Price: 30.5 gwei

🔐 Admin Address: 0x...
```

### Day 3: Verify on PolygonScan (30 min)

```bash
# 1. Go to: https://mumbai.polygonscan.com/
# 2. Search contract address (0xABC...DEF)
# 3. Verify you see:
#    - Contract creator (your address)
#    - Creation block
#    - Contract ABI
#    - Functions listed
```

### Day 4: Test Live Contract (2 hours)

```bash
# Create test script: scripts/test-live.ts

import { ethers } from "hardhat";

const contractAddress = "0x..."; // Your deployed contract

async function main() {
  const [signer] = await ethers.getSigners();

  const abi = [
    "function anchor(bytes32 merkleRoot) public returns (uint256)",
    "function isAnchored(bytes32 merkleRoot) public view returns (bool)",
    "function totalAnchors() public view returns (uint256)",
  ];

  const contract = new ethers.Contract(contractAddress, abi, signer);

  // Test 1: Submit merkle root
  const merkleRoot = "0x" + "1".padStart(64, "0");
  console.log("📤 Submitting merkle root...");
  const tx = await contract.anchor(merkleRoot);
  const receipt = await tx.wait();
  console.log(`✅ Submitted! TX: ${receipt.hash}`);

  // Test 2: Check if anchored
  const isAnchored = await contract.isAnchored(merkleRoot);
  console.log(`✓ Is anchored: ${isAnchored}`);

  // Test 3: Check total anchors
  const total = await contract.totalAnchors();
  console.log(`✓ Total anchors: ${total}`);
}

main().catch(console.error);
```

Run it:
```bash
npx hardhat run scripts/test-live.ts --network mumbai
```

---

## ✅ Week 1 Success Checklist

- [ ] **Day 1 (Today):**
  - [ ] Contract compiled successfully
  - [ ] All unit tests passing (45 tests)
  - [ ] >80% code coverage

- [ ] **Day 2:**
  - [ ] Wallet funded with testnet MATIC
  - [ ] Contract deployed to Mumbai testnet
  - [ ] Deployment details saved

- [ ] **Day 3:**
  - [ ] Contract verified on PolygonScan
  - [ ] ABI visible on block explorer
  - [ ] All functions listed

- [ ] **Day 4:**
  - [ ] Live contract tested
  - [ ] Merkle root submitted successfully
  - [ ] Verification working

- [ ] **Day 5:**
  - [ ] All Week 1 deliverables complete
  - [ ] Code reviewed (2+ reviewers)
  - [ ] Merged to main branch
  - [ ] Documentation updated (Bible Chapter 43 started)

---

## 🐛 Troubleshooting

### Problem: "Cannot find module 'ethers'"
```bash
npm install ethers
```

### Problem: "Compilation error: License identifier not provided"
Add this to top of .sol file:
```solidity
// SPDX-License-Identifier: MIT
```

### Problem: "Private key not found in .env"
Verify:
1. `.env` file exists in project root
2. Format is correct: `PRIVATE_KEY_TESTNET=0x...`
3. No extra spaces or quotes

### Problem: "Insufficient balance for gas"
Request more testnet MATIC: https://faucet.polygon.technology/

### Problem: "Network timeout"
Try alternative RPC:
```bash
# In .env:
POLYGON_MUMBAI_RPC=https://polygon-mumbai-rpc.allthatnode.com:8545
```

---

## 📚 Reference Files

| File | Purpose |
|------|---------|
| `contracts/ContentLedgerAnchor.sol` | Smart contract |
| `test/ContentLedgerAnchor.test.ts` | Unit tests |
| `scripts/deploy.ts` | Deployment script |
| `hardhat.config.ts` | Hardhat configuration |
| `.env` | Environment variables |

---

## 🎯 What's Next (After Week 1)

1. ✅ Week 1: Smart contract on testnet (CURRENT)
2. → Week 2: Web3.js PHP integration layer
3. → Week 3: Database schema + batch worker
4. → Week 4: Integration testing + monitoring

---

## 📞 Support

Questions?
- Smart contract logic: See comments in `contracts/ContentLedgerAnchor.sol`
- Testing: See `test/ContentLedgerAnchor.test.ts` for examples
- Deployment: Refer to `POLYGON-SETUP-GUIDE.md`
- Full roadmap: See `203-Complete-Plan.md`

---

**Status:** ✅ READY TO START DAY 1
**Duration:** 5 days
**Target Completion:** Feb 20, 2026
**Success Metric:** Contract deployed, all tests passing, verified on PolygonScan

🚀 **Let's go!**
