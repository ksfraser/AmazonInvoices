# Amazon Invoices Module - Admin Credentials System

## 🎯 **COMPLETE ADMIN CREDENTIAL MANAGEMENT SYSTEM**

### **Admin Screens Available:**

#### 1. **API Credentials Management** (`amazon_credentials.php`)
- **✅ Multi-Authentication Support:**
  - **Amazon SP-API** (Recommended for production)
  - **OAuth 2.0** (Alternative API access)
  - **Web Scraping** (Legacy fallback with warnings)

- **✅ Comprehensive Form Fields:**
  - SP-API: Client ID, Client Secret, Refresh Token, Region, Marketplace ID
  - AWS IAM: Role ARN, Access Keys (optional enhanced security)
  - OAuth: Client credentials, redirect URI, scopes
  - Scraping: Email/password with 2FA support

- **✅ Real-time Features:**
  - Live credential testing and validation
  - Token status monitoring with auto-refresh
  - API usage statistics and rate limiting
  - Connection health monitoring

#### 2. **Enhanced Settings** (`settings.php`)
- Basic module configuration
- Download paths and supplier setup
- Notification preferences
- System status checks

### **Backend Services:**

#### **AmazonCredentialService** - Complete credential management:
- **🔐 Security Features:**
  - AES-256-CBC encryption for sensitive data
  - Masked display for credential viewing
  - Audit trail for all credential changes
  - Input validation and sanitization

- **🧪 Testing & Validation:**
  - Live API connection testing
  - Credential format validation
  - Token expiration monitoring
  - Automatic refresh token handling

- **📊 Usage Monitoring:**
  - API call tracking and limits
  - Performance metrics
  - Error rate monitoring
  - Usage statistics dashboard

#### **Database Schema** - Extended for credentials:
- `amazon_credentials` - Encrypted credential storage
- `amazon_credential_tests` - Test result history
- `amazon_api_usage` - API usage tracking and analytics

### **Security Features:**

1. **🔒 Encryption at Rest:**
   - All sensitive credentials encrypted with AES-256-CBC
   - Unique encryption keys per installation
   - Secure key derivation from FA configuration

2. **🛡️ Input Validation:**
   - SP-API client ID format validation
   - OAuth URI and scope validation
   - Email format and password strength checks
   - SQL injection prevention

3. **🔍 Audit & Monitoring:**
   - Complete activity logging
   - Failed authentication tracking
   - Credential change history
   - Real-time security alerts

### **Integration Features:**

1. **🔗 Framework Agnostic:**
   - Works with FrontAccounting out of the box
   - Easy WordPress plugin conversion
   - Laravel package ready
   - Generic PHP framework support

2. **⚡ Real-time Updates:**
   - JavaScript auto-refresh for token status
   - AJAX credential testing
   - Live API usage monitoring
   - Automatic error detection

3. **📱 Responsive Design:**
   - Mobile-friendly admin interface
   - Progressive enhancement
   - Accessibility compliant
   - Modern CSS styling

### **Production Ready Features:**

#### **SP-API Integration** (Amazon's Official API):
```php
// Supports all Amazon marketplaces
'ATVPDKIKX0DER' => 'Amazon US'
'A1PA6795UKMFR9' => 'Amazon DE'  
'A13V1IB3VIYZZH' => 'Amazon FR'
// + 15 more marketplaces
```

#### **Error Handling & Recovery:**
- Automatic token refresh for expired credentials
- Graceful fallback for API failures
- Comprehensive error logging
- User-friendly error messages

#### **Rate Limiting & Compliance:**
- Built-in API rate limiting
- Usage quota monitoring
- Compliance with Amazon's terms
- Best practice recommendations

### **Quick Setup Guide:**

1. **Access Admin Screen:**
   ```
   FrontAccounting → Amazon → API Credentials
   ```

2. **Configure SP-API (Recommended):**
   - Register at Amazon Developer Console
   - Create SP-API application  
   - Get Client ID, Secret, and Refresh Token
   - Enter credentials and test connection

3. **Alternative Setup:**
   - OAuth 2.0 for enhanced security
   - Web scraping for legacy support (not recommended)

### **Architecture Benefits:**

- **SOLID Principles:** Single responsibility, dependency injection
- **Security First:** Encryption, validation, audit trails
- **Framework Agnostic:** Portable across PHP frameworks
- **Production Ready:** Error handling, monitoring, logging
- **Extensible:** Plugin architecture for custom features

### **Files Created/Updated:**

1. **Admin Interface:**
   - `amazon_invoices/amazon_credentials.php` - Main credential management screen
   - `amazon_invoices/includes/helpers.php` - Utility functions
   - `amazon_invoices/api/token_status.php` - Real-time status API

2. **Backend Services:**
   - `src/Services/AmazonCredentialService.php` - Core credential management
   - Updated `src/Services/DatabaseInstallationService.php` - Database schema

3. **Configuration:**
   - Updated `amazon_invoices/config.php` - Added credential menu

---

## 🚀 **READY FOR PRODUCTION**

The Amazon Invoices module now includes enterprise-grade credential management supporting:

✅ **Amazon SP-API** (Official, recommended)  
✅ **OAuth 2.0** (Alternative secure method)  
✅ **Legacy Scraping** (Fallback with warnings)  
✅ **Encrypted Storage** (AES-256-CBC)  
✅ **Real-time Monitoring** (Status, usage, errors)  
✅ **Multi-Framework Support** (FA, WordPress, Laravel)  
✅ **Production Security** (Validation, audit, compliance)

**Next Steps:** Configure your Amazon credentials through the admin interface and start downloading invoices!
