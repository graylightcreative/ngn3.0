// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

/**
 * @title ContentLedgerAnchor
 * @dev Immutable content ownership ledger anchored on blockchain
 * @notice Stores Merkle roots of content registry for cryptographic proof
 */

contract ContentLedgerAnchor {
    // ==================== EVENTS ====================

    event AnchorSubmitted(
        bytes32 indexed merkleRoot,
        uint256 indexed blockTimestamp,
        uint256 indexed blockNumber
    );

    event ContentVerified(
        bytes32 indexed contentHash,
        bool exists
    );

    // ==================== STATE ====================

    // Mapping of merkle root => submission timestamp
    mapping(bytes32 => uint256) public anchorTimestamps;

    // Mapping of merkle root => block number
    mapping(bytes32 => uint256) public anchorBlocks;

    // List of all submitted merkle roots (for iteration)
    bytes32[] public anchorHistory;

    // Admin address (for access control)
    address public admin;

    // Counter of total anchors submitted
    uint256 public totalAnchors;

    // ==================== MODIFIERS ====================

    modifier onlyAdmin() {
        require(msg.sender == admin, "Only admin can call this function");
        _;
    }

    // ==================== CONSTRUCTOR ====================

    constructor() {
        admin = msg.sender;
        totalAnchors = 0;
    }

    // ==================== MAIN FUNCTIONS ====================

    /**
     * @notice Submit a Merkle root to the blockchain
     * @param merkleRoot The Merkle root hash of the content registry
     * @return timestamp The block timestamp of the submission
     */
    function anchor(bytes32 merkleRoot) public onlyAdmin returns (uint256) {
        require(merkleRoot != bytes32(0), "Invalid merkle root");
        require(anchorTimestamps[merkleRoot] == 0, "Root already anchored");

        // Store submission timestamp
        anchorTimestamps[merkleRoot] = block.timestamp;
        anchorBlocks[merkleRoot] = block.number;

        // Add to history
        anchorHistory.push(merkleRoot);
        totalAnchors++;

        // Emit event
        emit AnchorSubmitted(merkleRoot, block.timestamp, block.number);

        return block.timestamp;
    }

    /**
     * @notice Verify if a content hash is included in anchored registry
     * @dev This is a placeholder - actual verification would require
     *      Merkle proof submission and validation
     * @param contentHash The content hash to verify
     * @return exists True if hash is in anchored registry
     */
    function verify(bytes32 contentHash) public view returns (bool) {
        // Placeholder implementation
        return anchorHistory.length > 0;
    }

    /**
     * @notice Get the timestamp when a merkle root was anchored
     * @param merkleRoot The merkle root to query
     * @return timestamp The Unix timestamp of anchoring
     */
    function getTimestamp(bytes32 merkleRoot) public view returns (uint256) {
        require(anchorTimestamps[merkleRoot] != 0, "Merkle root not found");
        return anchorTimestamps[merkleRoot];
    }

    /**
     * @notice Get the block number when a merkle root was anchored
     * @param merkleRoot The merkle root to query
     * @return blockNum The block number of anchoring
     */
    function getBlockNumber(bytes32 merkleRoot) public view returns (uint256) {
        require(anchorBlocks[merkleRoot] != 0, "Merkle root not found");
        return anchorBlocks[merkleRoot];
    }

    /**
     * @notice Get total number of anchors submitted
     * @return count The total number of merkle roots anchored
     */
    function getAnchorCount() public view returns (uint256) {
        return totalAnchors;
    }

    /**
     * @notice Check if a merkle root has been anchored
     * @param merkleRoot The merkle root to check
     * @return isAnchored True if root has been submitted
     */
    function isAnchored(bytes32 merkleRoot) public view returns (bool) {
        return anchorTimestamps[merkleRoot] != 0;
    }

    // ==================== ADMIN FUNCTIONS ====================

    /**
     * @notice Transfer admin rights to new address
     * @param newAdmin The new admin address
     */
    function transferAdmin(address newAdmin) public onlyAdmin {
        require(newAdmin != address(0), "Invalid address");
        admin = newAdmin;
    }
}
