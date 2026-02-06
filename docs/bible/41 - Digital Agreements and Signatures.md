# 41 - Digital Agreements and Signatures

## 41.1 Overview
The NGN Digital Agreement System is a specialized infrastructure designed to present legal documents to users and capture binding digital signatures. This system ensures that every participant on the platform—whether an Artist, Label, or Venue—operates under a verified legal framework, protecting NGN LLC and its stakeholders from liability while ensuring clear rights and revenue distributions.

## 41.2 Core Components

### 1. AgreementService (`NGN\Lib\Services\Legal\AgreementService`)
The backend engine responsible for:
*   Retrieving active agreement templates.
*   Checking signature status for specific users and versions.
*   Recording new signatures with high-integrity audit data.
*   Managing template versions.

### 2. Agreement Viewer (`lib/partials/legal/agreement-viewer.php`)
A secure, responsive UI component that:
*   Presents the agreement in a scrollable container.
*   Requires active confirmation (checkbox) before enabling the signature button.
*   Constitutes a binding digital signature upon submission.
*   Displays signature status if the agreement has already been accepted.

### 3. Admin Digital Signatures Console
Located at `/admin/digital-signatures/`, this dashboard provides:
*   **Audit Log**: A real-time record of all signatures, including IP addresses and cryptographic hashes.
*   **Document Management**: A tool to create, version, and update agreement templates (e.g., Artist Distribution Agreement, Terms of Service).

## 41.3 Data Model

### `agreement_templates`
Stores the content and versioning of legal documents.
*   `slug`: Unique identifier used for routing (e.g., `artist-onboarding`).
*   `body`: The full HTML content of the agreement.
*   `version`: SemVer-style versioning to track legal changes.
*   `is_active`: Toggle to enable/disable specific templates.

### `agreement_signatures`
The immutable audit log of accepted terms.
*   `agreement_hash`: A **SHA-256 hash** of the agreement body at the exact moment of signing. This proves that the user signed a specific version of the text, even if the template is updated later.
*   `ip_address` & `user_agent`: Technical metadata used to verify the origin of the signature for legal auditing.
*   `signed_at`: The definitive timestamp of the agreement.

## 41.4 Usage for Developers

### Presenting an Agreement
To require a user to sign an agreement, direct them to the standardized route:
`https://beta.nextgennoise.com/agreement/{slug}`

### Programmatic Check (Backend)
```php
use NGN\Lib\Services\Legal\AgreementService;

$service = new AgreementService($db);
if (!$service->hasSigned($userId, 'artist-onboarding')) {
    // Redirect to agreement page
    header("Location: /agreement/artist-onboarding");
    exit;
}
```

### Programmatic Check (Frontend Partial)
The system is integrated into `public/index.php`. You can check the `$data['agreement_signed']` variable when in the `agreement` view.

## 41.5 Security & Integrity Protocols
*   **Document Fingerprinting**: Every signature is tied to a SHA-256 hash of the content. If a legal dispute arises, the hash can be compared against the historical template to prove exactly what the user saw.
*   **Double-Opt-In**: Users must check a confirmation box explicitly stating they understand the digital signature is binding before they can accept.
*   **Immutable Logs**: Signatures cannot be deleted or modified through the UI, ensuring a permanent audit trail for compliance officers and legal counsel.
*   **Version Enforcement**: When a legal template is updated to a new version, the `hasSigned` check can be used to re-prompt existing users to accept the new terms before proceeding.
