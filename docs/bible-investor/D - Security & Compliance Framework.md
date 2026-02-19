# Appendix D: Security & Compliance Framework

## Executive Summary

NGN operates with **institutional-grade security and compliance** across data protection, financial services, music licensing, and international privacy regulations. Security layers: (1) encryption in transit (TLS 1.3) and at rest (field-level), (2) authentication (JWT + 2FA), (3) rate limiting (10,000 req/day per user), (4) audit logging (all transactions recorded). Compliance frameworks: PCI DSS (payment processing), SOC 2 Type II (operational controls), GDPR/CCPA (privacy), music licensing (mechanical, performance, sync). Third-party certifications: D&O insurance ($5M+), cyber liability insurance ($2M+), annual security audits. Result: Artists trust NGN with earnings; partners trust NGN with data; investors trust NGN won't face regulatory penalties.

---

## 1. Security Architecture

### 1.1 Data Classification

**Tier 1: Public** (no encryption required)
- Chart rankings
- Artist bios/profiles
- Song metadata (title, genre, duration)
- Aggregated statistics (chart trends, genre popularity)

**Tier 2: User Data** (encrypted in transit)
- User profiles (username, bio, preferences)
- Engagement history (songs played, tips given)
- Social connections (followers, comments)

**Tier 3: Payment Data** (encrypted at rest + in transit)
- Credit card information (tokenized, never stored plain-text)
- Bank account details (encrypted AES-256)
- Payout history (transaction records)

**Tier 4: Financial Records** (encrypted at rest + in transit + audit logged)
- Artist earnings (never exposed to other artists)
- Rights allocations
- Royalty calculations
- Tax documents (1099 records)

**Tier 5: Sensitive Systems** (air-gapped access logs)
- Database credentials
- API keys
- Stripe Connect tokens
- Admin access logs

### 1.2 Encryption Standards

**In Transit**:
- ✅ **TLS 1.3** (all connections must be HTTPS)
- ✅ **Cipher Suite**: TLS_AES_256_GCM_SHA384 (government-grade)
- ✅ **Certificate Authority**: DigiCert (trusted by browsers)
- ✅**HSTS Header**: Enforce HTTPS for 1 year (no mixed content)

**At Rest**:
- ✅ **Database Encryption**: MySQL field-level (AES-256 for payment data)
- ✅ **File Storage**: S3 server-side encryption (AES-256)
- ✅ **Backup Encryption**: Encrypted backups, held for 90 days
- ✅ **Key Management**: AWS KMS (keys rotated quarterly)

### 1.3 Authentication & Access Control

**User Authentication**:
- ✅ **Password Security**: Bcrypt hashing (not plain-text)
- ✅ **2FA Optional**: TOTP/SMS for high-value accounts
- ✅ **JWT Tokens**: 1-hour expiration (force re-auth daily)
- ✅ **Session Management**: Revocable tokens (user can logout anywhere)

**API Authentication**:
- ✅ **API Keys**: For server-to-server (radio integrations, label partners)
- ✅ **Rate Limiting**: 10,000 requests/day per user (prevent abuse)
- ✅ **IP Whitelisting**: For sensitive endpoints (admin, payouts)
- ✅ **OAuth 2.0**: For third-party integrations (future)

**Admin Access**:
- ✅ **Role-Based Access Control (RBAC)**: Artist, moderator, analyst, admin
- ✅ **Principle of Least Privilege**: Each role gets minimum required permissions
- ✅ **Audit Logging**: All admin actions logged (who changed what, when)
- ✅ **Segregation of Duties**: No single person can approve + execute payouts

### 1.4 Network Security

**Firewall Rules**:
- ✅ **Public APIs**: Open to Internet (rate-limited)
- ✅ **Admin Panel**: IP-restricted (only office + VPN)
- ✅ **Database**: Private network (no direct Internet access)
- ✅ **Payment Processing**: PCI-compliant network (isolated)

**DDoS Protection**:
- ✅ **CloudFlare**: DDoS mitigation + CDN
- ✅ **Rate Limiting**: 10K req/day per user (prevents flood attacks)
- ✅ **Auto-Scaling**: Traffic spike? Servers auto-scale (prevent crash)

### 1.5 Application Security

**Input Validation**:
- ✅ **No SQL Injection**: Parameterized queries (not string concatenation)
- ✅ **No XSS**: Output escaping (user input can't execute scripts)
- ✅ **CSRF Protection**: Token-based (prevents cross-site request forgery)
- ✅ **File Upload Security**: Whitelist file types, scan for malware

**Error Handling**:
- ✅ **Generic Error Messages**: Don't leak system info ("Invalid login" not "User not found")
- ✅ **Logging**: Detailed logs for debugging (not shown to users)
- ✅ **Monitoring**: Alert on unusual patterns (100 failed logins? Investigate)

---

## 2. Compliance Frameworks

### 2.1 Payment Compliance (PCI DSS)

**PCI DSS Level 2** (highest for companies our size)

**Requirements**:
- ✅ **Tokenization**: Credit cards converted to tokens (we never see the number)
- ✅ **Encryption**: Payment data encrypted at all times
- ✅ **Access Control**: Only authorized personnel can access payment systems
- ✅ **Audit Trails**: Every transaction logged + auditable
- ✅ **Annual Audit**: Third-party auditor validates compliance

**Why It Matters**:
- Stripe handles PCI for us (we don't store card data)
- Artists trust their payment methods are safe
- Partners (labels, radio) require PCI compliance for data sharing

**Certification**: Annual PCI compliance report from Stripe

---

### 2.2 Operational Controls (SOC 2 Type II)

**Scope**: Security, Availability, Processing Integrity, Confidentiality, Privacy

**Key Controls**:
- ✅ **Access Controls**: Passwords, 2FA, audit logs
- ✅ **Change Management**: Code review + testing before deployment
- ✅ **Backup & Recovery**: Daily backups, tested recovery quarterly
- ✅ **Incident Response**: 24/7 incident team, documented response plan
- ✅ **Monitoring**: Continuous security monitoring + alerting

**Why It Matters**:
- Enterprise customers (labels, radio) require SOC 2 for compliance
- Investors want proof of operational maturity
- Insurance companies use SOC 2 to underwrite cyber policies

**Certification**: Annual SOC 2 Type II audit (6-month report validity)

---

### 2.3 Privacy Regulations (GDPR / CCPA)

**GDPR Compliance** (EU users):
- ✅ **Data Rights**: Users can request, access, delete personal data
- ✅ **Consent**: Explicit opt-in for email/marketing
- ✅ **Privacy Policy**: Transparent about data use (publicly available)
- ✅ **Data Processing Agreement**: When using vendors (Stripe, Mailchimp, etc.)
- ✅ **Data Breach Notification**: 72 hours to notify (if breach occurs)

**CCPA Compliance** (California users):
- ✅ **Right to Know**: Users can see what data we have
- ✅ **Right to Delete**: Users can request data deletion
- ✅ **Right to Opt-Out**: Can opt out of "sale" of personal data
- ✅ **No Discrimination**: Can't punish users for exercising rights

**COPPA Compliance** (if any users under 13):
- ✅ **Parental Consent**: Require parent/guardian approval
- ✅ **Minimal Data Collection**: Don't collect more than necessary
- ✅ **No Marketing**: Can't target ads at children

**Why It Matters**:
- Legal requirement (fines up to 4% revenue for GDPR violations)
- Artist trust (their data is private)
- Global expansion (can operate in EU/California legally)

**Governance**: Privacy officer + data protection impact assessments (DPIA)

---

### 2.4 Music Licensing Compliance

**Mechanical Licenses** (Composition ownership)
- ✅ **Requirement**: License compositions (songs, not recordings)
- ✅ **Payer**: NGN (splits payment with labels/publishers)
- ✅ **Rate**: Statutory rate ($0.091 per song per stream, US rate)
- ✅ **Reporting**: Monthly reports to Harry Fox Agency / MLC

**Performance Licenses** (Public performance)
- ✅ **Requirement**: Permit public performance (streaming counts)
- ✅ **Payer**: NGN (paid to ASCAP/BMI/SESAC)
- ✅ **Rate**: Pro-rata share of revenue
- ✅ **Reporting**: Monthly plays to performing rights orgs

**Sync Licenses** (Synchronization)
- ✅ **Requirement**: License for use in videos, podcasts, etc.
- ✅ **Scope**: NGN social feed videos, artist showcase videos
- ✅ **Rate**: Pre-negotiated with rights holders
- ✅ **Reporting**: Manual tracking (smaller volume)

**Rights Registry** (Ownership verification)
- ✅ **ISRC Registration**: Register all songs with ISRC (international ID)
- ✅ **Rights Ledger**: Maintain accurate ownership records
- ✅ **SoundExchange**: Register for digital performance royalties
- ✅ **Publishing Registry**: Link compositions to publishers

**Why It Matters**:
- Legal requirement (copyright law)
- Artist trust (proper licensing protects them)
- Label partnerships require license verification

**Governance**: Music lawyer on staff (or retained counsel) for licensing agreements

---

### 2.5 Tax Compliance

**1099 Reporting** (US contractors)
- ✅ **Requirement**: Issue 1099-NEC/1099-MISC if artist earned >$600/year
- ✅ **Timing**: File by January 31 (for prior year)
- ✅ **Reporting**: E-file to IRS + mail to artist
- ✅ **Backup Withholding**: If artist doesn't provide SSN/EIN, withhold 24%

**Sales Tax** (State-by-state)
- ✅ **Scope**: Collected on Spark tips + subscriptions
- ✅ **Remittance**: File monthly/quarterly per state requirements
- ✅ **Nexus**: Where NGN has presence (every US state if subscriptions sold)
- ✅ **Exemptions**: Tax-exempt status + artist purchases (generally not exempt)

**International** (Future expansion)
- ✅ **VAT/GST**: Collected on EU/Canada/Australia subscriptions
- ✅ **Withholding**: May need to withhold on international artist payouts
- ✅ **Reporting**: File monthly VAT returns per country

**Why It Matters**:
- Legal requirement (tax evasion = criminal)
- Artist trust (transparency on deductions)
- Government relations (IRS doesn't like surprises)

**Governance**: Accountant on staff (or outsourced firm) handles filings

---

## 3. Security Incidents & Response

### 3.1 Incident Response Plan

**Tier 1: Low-Severity** (Customer data, non-financial)
- Response time: 24 hours
- Actions: Investigate, patch, inform customer
- Example: Unauthorized account access (password reset)

**Tier 2: Medium-Severity** (Payment data, >100 customers affected)
- Response time: 4 hours
- Actions: Isolate system, notify CEO + legal, file incident report
- Example: Payment processor integration fails (temporary)

**Tier 3: High-Severity** (Data breach, regulatory trigger)
- Response time: 1 hour
- Actions: Full incident team, external forensics, notify authorities
- Example: Database breach exposing artist earnings

**Tier 4: Critical** (Service down, financial loss)
- Response time: 15 minutes
- Actions: All-hands incident war room, CEO communication, media prep
- Example: DDoS attack, network compromise

### 3.2 Breach Notification

**Discovery** → **Assessment** → **Notification**

1. Discover incident (monitoring alert or user report)
2. Isolate affected system (prevent spread)
3. Assess scope (how many users, what data exposed?)
4. Notify internally (CEO, legal, security)
5. Notify authorities (if required by law)
   - GDPR: Notify within 72 hours
   - CCPA: Notify customers + California AG
   - PCI: Notify Stripe, payment processors
6. Notify customers (transparent communication)
7. Publish postmortem (what happened, what we'll do differently)

---

## 4. Third-Party Certifications & Insurance

### 4.1 Insurance Coverage

**Directors & Officers (D&O) Liability**: $5M+ coverage
- Covers: Shareholder lawsuits, employment lawsuits, regulatory fines
- Premium: $50-75K/year
- Required by: Series A investors (standard)

**Cyber Liability Insurance**: $2M+ coverage
- Covers: Data breach costs, notification expenses, legal defense
- Premium: $25-40K/year
- Required by: Enterprise partners with data requirements

**Professional Liability (E&O)**: $1M+ coverage
- Covers: Errors in calculations, negligence, failure of service
- Premium: $15-25K/year
- Required by: Partners handling artist data

**Fiduciary Liability**: $2M+ coverage
- Covers: Mishandling artist funds, wrongful denial of payments
- Premium: $20-30K/year
- Specific to music industry (critical for artist trust)

**Total Insurance Cost**: ~$150K/year

**Why It Matters**:
- Reduces financial risk if something goes wrong
- Signals to artists that they're protected
- Required by investors (risk mitigation)
- Vendor requirement (labels require E&O proof)

### 4.2 Third-Party Audits

**Annual Audits**:
- ✅ **PCI DSS Audit**: $10-15K (Stripe handles, we get report)
- ✅ **SOC 2 Type II Audit**: $15-25K (Big Four accounting firm)
- ✅ **Security Penetration Test**: $20-30K (external security firm)
- ✅ **Financial Audit**: $10-20K (if fundraising, required)

**On-Demand Audits**:
- ✅ **Vendor Security Assessments**: Partner audit requirements
- ✅ **Compliance Reviews**: Regulatory/legal requirements
- ✅ **Code Audits**: Post-deployment security review

---

## 5. Security Incident Examples & Responses

### 5.1 Scenario: Unauthorized Artist Access

**Incident**: Hacker gains access to an artist's account, claims their earnings.

**Response**:
1. Isolate account (disable login, freeze payouts)
2. Notify artist (investigate claim, restore access)
3. Audit trail (check what was accessed)
4. Password reset (force 2FA re-enrollment)
5. Review logs (was it account takeover or data breach?)

**Prevention**:
- 2FA enforcement for high-value accounts
- Unusual login alerts (different country, device)
- Transaction limits (can't withdraw >$10K without approval)

---

### 5.2 Scenario: Payment Processor Breach

**Incident**: Stripe reports a breach affecting 1,000 NGN users.

**Response**:
1. Verify scope (Stripe confirms which users affected)
2. Notify affected users (within 24 hours)
3. Notify Stripe/FBI (breach reporting requirements)
4. Update security controls (implement tokenization if not already)
5. Monitor accounts (watch for fraudulent activity)

**Prevention**:
- NGN doesn't store card data (Stripe does)
- We rely on Stripe's security (PCI compliance)
- Insurance covers notification costs

---

### 5.3 Scenario: Data Breach Exposing Artist Earnings

**Incident**: Hacker gains database access, downloads artist earnings table.

**Response**:
1. Detect breach (monitoring alert on database access)
2. Isolate database (disconnect from network)
3. Assess scope (which data was accessed?)
4. Notify authorities (FBI, state AG if required)
5. Notify affected artists (within 72 hours)
6. Offer credit monitoring (1 year free service)
7. Legal action (cooperate with law enforcement)

**Prevention**:
- Database encryption (even if stolen, data is encrypted)
- Access logging (detect unusual queries)
- Regular backups (restore from clean state)

---

## 6. Compliance Roadmap

### 2026 Q1: Foundation
- ✅ PCI DSS Level 2 (payment compliance)
- ✅ GDPR/CCPA implementation (privacy controls)
- ✅ Music licensing agreements (mechanical, performance)
- ✅ Annual security audit

### 2026 Q2: Scale
- ✅ SOC 2 Type II audit (operational controls)
- ✅ Cyber insurance increase ($5M → $10M)
- ✅ International expansion prep (VAT, localization)
- ✅ Penetration testing (annual)

### 2026 Q3: Operations
- ✅ Incident response plan documentation
- ✅ Data protection officer (DPO) hiring
- ✅ Compliance training (all staff)
- ✅ Vendor security assessments

### 2027: Enterprise Ready
- ✅ ISO 27001 certification (information security)
- ✅ HIPAA-lite controls (if health data in future)
- ✅ International compliance (UK, Canada, Australia)
- ✅ Regulatory relationship (FTC, FCC liaison)

---

## 7. For Investors: Why This Matters

✅ **Legal Risk Mitigation**: Compliance prevents fines, lawsuits, shutdowns
✅ **Artist Trust**: Security proves we protect their money + data
✅ **Partner Requirements**: Enterprise customers require SOC 2, PCI, insurance
✅ **Valuation**: Institutional buyers (acquirers) value compliance infrastructure
✅ **Insurance**: Coverage reduces Series A valuation impact of security incidents

---

## 8. Related Documentation

- **Chapter 23**: Governance Structure (Audit Committee oversight)
- **Chapter 14**: Rights Ledger (ownership + dispute resolution)
- **Chapter 13**: Royalty System (financial controls)
- **Appendix C**: Database Schema (data classification)

---

*For detailed security runbooks and incident response procedures, see Technical Bible.*
