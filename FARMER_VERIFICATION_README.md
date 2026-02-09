# Farmer Verification System

This document describes the implementation of the Farmer Profile Page and Admin Verification functionality for the BantayAni system.

## Overview

The farmer verification system allows farmers to upload certificates for admin review, and enables administrators to verify farmer credentials through a comprehensive interface.

## Files Created/Modified

### Database
- `backend_sql/farmer_verification.sql` - SQL script for the farmer verification table

### Farmer Interface
- `farmer/profile.php` - Comprehensive farmer profile page with verification status
- `farmer/upload_certificate.php` - Certificate upload interface for farmers

### Admin Interface  
- `admin/verify_farmers.php` - Admin verification interface to review certificates

### Helper Functions
- `includes/verification_helper.php` - Utility functions for verification status display

### Modified Files
- `admin/index.php` - Updated navigation to include verification link

## Database Schema

### farmer_verification Table
- `verification_id` - Primary key
- `farmer_id` - Foreign key to users table
- `certificate_type` - Type of certificate (Business Permit, Agricultural License, etc.)
- `certificate_name` - Custom name/title for the certificate
- `certificate_path` - File path to uploaded certificate
- `status` - Verification status (Pending, Approved, Rejected)
- `submitted_at` - Timestamp when certificate was submitted
- `reviewed_at` - Timestamp when admin reviewed the certificate
- `reviewed_by` - Admin user ID who reviewed the certificate
- `admin_notes` - Notes from admin regarding the verification

## Features

### Farmer Profile Page (`farmer/profile.php`)
- **Personal Information Display**: Shows farmer's personal details with inline editing
- **Farm Information**: Displays farm details with image upload capability
- **Verification Status**: Shows current verification status with progress tracking
- **Statistics**: Displays farm statistics (crop types, total quantity, ratings)
- **Certificate Management**: Lists submitted certificates with their status
- **Responsive Design**: Mobile-friendly interface

### Certificate Upload (`farmer/upload_certificate.php`)
- **Multiple Certificate Types**: Support for Business Permit, Agricultural License, Tax ID, and Others
- **File Validation**: Validates file types (PDF, JPG, PNG, DOC, DOCX) and size (5MB limit)
- **Upload History**: Shows all submitted certificates with their status
- **Admin Notes Display**: Shows feedback from administrators

### Admin Verification (`admin/verify_farmers.php`)
- **Dashboard Statistics**: Overview of verification statistics
- **Pending Reviews**: Lists all certificates pending admin review
- **Certificate Viewing**: Direct viewing of uploaded certificates
- **Approval/Rejection**: Admin can approve or reject certificates with notes
- **Verification History**: Complete history of all verifications
- **Auto-Verification**: Farmers are automatically marked as verified when all certificates are approved

### Helper Functions (`includes/verification_helper.php`)
- `getVerificationStatusBadge()` - Returns HTML for verification status badges
- `getCertificateStatusBadge()` - Returns HTML for certificate status badges
- `canFarmerBeVerified()` - Checks if farmer meets verification criteria
- `getFarmerVerificationSummary()` - Gets verification statistics for a farmer
- `getVerificationProgress()` - Calculates verification progress percentage
- `getVerificationRequirementsText()` - Human-readable verification status
- `formatCertificateType()` - Formats certificate types for display
- `getVerificationStatusClass()` - Returns CSS classes for status styling
- `hasSubmittedCertificates()` - Checks if farmer has submitted any certificates
- `getNextVerificationStep()` - Provides guidance for next verification step

## Verification Workflow

1. **Farmer Uploads Certificate**: Farmer uploads certificate through upload interface
2. **Admin Review**: Admin reviews certificate in verification interface
3. **Decision**: Admin approves or rejects certificate with optional notes
4. **Auto-Verification**: System automatically verifies farmer when all certificates are approved
5. **Status Update**: Farmer profile reflects verification status

## File Structure

```
bantayani/
├── backend_sql/
│   └── farmer_verification.sql
├── farmer/
│   ├── profile.php
│   └── upload_certificate.php
├── admin/
│   ├── index.php (modified)
│   └── verify_farmers.php
├── includes/
│   └── verification_helper.php
├── uploads/
│   └── certificates/ (auto-created)
└── images/
    ├── default-avatar.png (placeholder)
    └── default-farm.png (placeholder)
```

## Security Features

- **File Type Validation**: Only allowed file types can be uploaded
- **File Size Limits**: Maximum file size of 5MB
- **SQL Injection Protection**: Uses prepared statements
- **Session Validation**: Proper role-based access control
- **XSS Protection**: All output is properly escaped

## Installation

1. **Database Setup**: Run the SQL script `backend_sql/farmer_verification.sql`
2. **Directory Permissions**: Ensure `uploads/certificates/` is writable
3. **Default Images**: Replace placeholder images in `images/` directory

## Usage

### For Farmers:
1. Log in as a farmer
2. Go to Profile page
3. Click "Upload Certificate" 
4. Fill in certificate details and upload file
5. Wait for admin review
6. Check profile for verification status

### For Admins:
1. Log in as admin
2. Go to Verification from navigation
3. Review pending certificates
4. Approve or reject with notes
5. Monitor verification statistics

## Styling

The system uses a consistent design language with:
- Color-coded status badges
- Responsive grid layouts
- Modern card-based interface
- Smooth transitions and hover effects
- Mobile-optimized views

## Future Enhancements

- Email notifications for verification status changes
- Bulk certificate approval
- Certificate expiration tracking
- Advanced filtering and search in admin interface
- Certificate template generation
