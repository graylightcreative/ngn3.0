// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC721/extensions/ERC721URIStorage.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

/**
 * @title ContentCertificateNFT
 * @dev ERC721 token representing content ownership certificates
 */
contract ContentCertificateNFT is ERC721URIStorage, Ownable {
    uint256 private _nextTokenId;

    // Mapping from content hash to token ID
    mapping(bytes32 => uint256) public contentHashToTokenId;

    event CertificateMinted(address indexed artist, uint256 indexed tokenId, bytes32 indexed contentHash, string tokenURI);

    constructor() ERC721("NGN Content Certificate", "NGNCERT") Ownable(msg.sender) {}

    /**
     * @notice Mint a new content ownership certificate
     * @param artist The address of the artist/owner
     * @param contentHash The cryptographic hash of the content
     * @param tokenURI IPFS URI containing the certificate metadata
     * @return uint256 The new token ID
     */
    function mintCertificate(address artist, bytes32 contentHash, string memory tokenURI)
        public
        onlyOwner
        returns (uint256)
    {
        require(contentHash != bytes32(0), "Invalid content hash");
        require(contentHashToTokenId[contentHash] == 0, "Content already has a certificate");

        uint256 newItemId = ++_nextTokenId;

        _mint(artist, newItemId);
        _setTokenURI(newItemId, tokenURI);
        
        contentHashToTokenId[contentHash] = newItemId;

        emit CertificateMinted(artist, newItemId, contentHash, tokenURI);

        return newItemId;
    }

    /**
     * @notice Check if a content hash has an associated certificate
     * @param contentHash The content hash to check
     * @return bool True if certificate exists
     */
    function hasCertificate(bytes32 contentHash) public view returns (bool) {
        return contentHashToTokenId[contentHash] > 0;
    }
}
