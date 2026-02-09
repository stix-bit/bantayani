<?php
/**
 * Verification Helper Functions
 * Contains utility functions for farmer verification status display
 */

/**
 * Get verification status badge HTML
 * 
 * @param bool $is_verified Whether the farmer is verified
 * @return string HTML for status badge
 */
function getVerificationStatusBadge($is_verified) {
    if ($is_verified) {
        return '<span class="verification-status status-verified">Verified Farmer</span>';
    } else {
        return '<span class="verification-status status-not-verified">Not Verified</span>';
    }
}

/**
 * Get certificate status badge HTML
 * 
 * @param string $status Certificate status (Pending, Approved, Rejected)
 * @return string HTML for status badge
 */
function getCertificateStatusBadge($status) {
    $status_class = 'status-' . strtolower($status);
    return '<span class="document-status ' . $status_class . '">' . htmlspecialchars($status) . '</span>';
}

/**
 * Check if farmer can be marked as verified
 * 
 * @param mysqli $conn Database connection
 * @param int $farmer_id Farmer user ID
 * @return bool True if all certificates are approved
 */
function canFarmerBeVerified($conn, $farmer_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total, 
               SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved
        FROM farmer_verification 
        WHERE farmer_id = ?
    ");
    $stmt->bind_param('i', $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result['total'] > 0 && $result['total'] == $result['approved'];
}

/**
 * Get farmer verification summary
 * 
 * @param mysqli $conn Database connection
 * @param int $farmer_id Farmer user ID
 * @return array Verification summary statistics
 */
function getFarmerVerificationSummary($conn, $farmer_id) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_certificates,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
        FROM farmer_verification 
        WHERE farmer_id = ?
    ");
    $stmt->bind_param('i', $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result;
}

/**
 * Get verification progress percentage
 * 
 * @param mysqli $conn Database connection
 * @param int $farmer_id Farmer user ID
 * @return int Progress percentage (0-100)
 */
function getVerificationProgress($conn, $farmer_id) {
    $summary = getFarmerVerificationSummary($conn, $farmer_id);
    
    if ($summary['total_certificates'] == 0) {
        return 0;
    }
    
    return round(($summary['approved'] / $summary['total_certificates']) * 100);
}

/**
 * Get verification requirements text
 * 
 * @param mysqli $conn Database connection
 * @param int $farmer_id Farmer user ID
 * @return string Human-readable verification status
 */
function getVerificationRequirementsText($conn, $farmer_id) {
    $summary = getFarmerVerificationSummary($conn, $farmer_id);
    
    if ($summary['total_certificates'] == 0) {
        return 'No certificates submitted yet';
    }
    
    if ($summary['pending'] > 0) {
        return $summary['pending'] . ' certificate(s) pending review';
    }
    
    if ($summary['rejected'] > 0) {
        return $summary['rejected'] . ' certificate(s) rejected. Please resubmit.';
    }
    
    if ($summary['approved'] > 0 && $summary['approved'] == $summary['total_certificates']) {
        return 'All certificates approved - Farmer verified';
    }
    
    return 'Verification in progress';
}

/**
 * Format certificate type for display
 * 
 * @param string $type Certificate type from database
 * @return string Formatted certificate type
 */
function formatCertificateType($type) {
    $types = [
        'Business Permit' => 'Business Permit',
        'Agricultural License' => 'Agricultural License',
        'Tax Identification' => 'Tax Identification',
        'Others' => 'Other Certificate'
    ];
    
    return isset($types[$type]) ? $types[$type] : htmlspecialchars($type);
}

/**
 * Get CSS classes for verification status
 * 
 * @param string $status Status string
 * @return string CSS class names
 */
function getVerificationStatusClass($status) {
    $classes = [
        'Pending' => 'status-pending',
        'Approved' => 'status-approved',
        'Rejected' => 'status-rejected',
        'verified' => 'status-verified',
        'not-verified' => 'status-not-verified'
    ];
    
    return isset($classes[$status]) ? $classes[$status] : 'status-pending';
}

/**
 * Check if farmer has submitted any certificates
 * 
 * @param mysqli $conn Database connection
 * @param int $farmer_id Farmer user ID
 * @return bool True if certificates exist
 */
function hasSubmittedCertificates($conn, $farmer_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM farmer_verification WHERE farmer_id = ?");
    $stmt->bind_param('i', $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result['count'] > 0;
}

/**
 * Get next verification step for farmer
 * 
 * @param mysqli $conn Database connection
 * @param int $farmer_id Farmer user ID
 * @return string Next step message
 */
function getNextVerificationStep($conn, $farmer_id) {
    $summary = getFarmerVerificationSummary($conn, $farmer_id);
    
    if ($summary['total_certificates'] == 0) {
        return 'Upload your first certificate to begin verification';
    }
    
    if ($summary['pending'] > 0) {
        return 'Waiting for admin review of submitted certificates';
    }
    
    if ($summary['rejected'] > 0) {
        return 'Some certificates were rejected. Please upload new ones';
    }
    
    if ($summary['approved'] > 0 && $summary['approved'] == $summary['total_certificates']) {
        return 'Verification complete! You are a verified farmer';
    }
    
    return 'Continue uploading certificates for complete verification';
}
?>
