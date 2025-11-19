<?php
/**
 * Status Display Helper
 * 
 * This file contains helper functions to convert database status values
 * to user-friendly display text.
 * 
 * OPTIONAL: You can include this file in your config.php or pages to use
 * the helper functions for consistent status display.
 */

/**
 * Convert database status value to user-friendly display text
 * 
 * @param string $dbStatus The status value from database
 * @return string The user-friendly display text
 */
function getStatusDisplayText($dbStatus) {
    $statusMap = [
        'Approved' => 'Approved',
        'Pending' => 'Pending',
        'Disapproved' => 'Declined'  // Display as "Declined" instead of "Disapproved"
    ];
    
    return $statusMap[$dbStatus] ?? $dbStatus;
}

/**
 * Get HTML badge for patient status with proper styling
 * 
 * @param string $dbStatus The status value from database
 * @param bool $darkMode Whether to use dark mode styling
 * @return string HTML span element with styled badge
 */
function getStatusBadge($dbStatus, $darkMode = false) {
    $displayText = getStatusDisplayText($dbStatus);
    
    // Determine badge colors based on actual database status
    $classes = 'inline-flex px-2 py-1 text-xs font-semibold rounded-full ';
    
    switch ($dbStatus) {
        case 'Approved':
            $classes .= 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            $icon = '✅';
            break;
        case 'Pending':
            $classes .= 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
            $icon = '⏳';
            break;
        case 'Disapproved':
            $classes .= 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            $icon = '❌';
            break;
        default:
            $classes .= 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
            $icon = '';
    }
    
    return sprintf(
        '<span class="%s">%s %s</span>',
        htmlspecialchars($classes),
        $icon,
        htmlspecialchars($displayText)
    );
}

/**
 * Get status dropdown options HTML
 * 
 * @param string $selectedStatus The currently selected status
 * @param bool $includeIcons Whether to include emoji icons
 * @return string HTML option elements
 */
function getStatusDropdownOptions($selectedStatus = '', $includeIcons = false) {
    $options = [
        'Pending' => $includeIcons ? '⏳ Pending - Needs review' : 'Pending',
        'Approved' => $includeIcons ? '✅ Approved - Ready for treatment' : 'Approved',
        'Disapproved' => $includeIcons ? '❌ Declined - Cannot proceed' : 'Declined'
    ];
    
    $html = '';
    foreach ($options as $value => $label) {
        $selected = ($selectedStatus === $value) ? ' selected' : '';
        $html .= sprintf(
            '<option value="%s"%s>%s</option>' . "\n",
            htmlspecialchars($value),
            $selected,
            htmlspecialchars($label)
        );
    }
    
    return $html;
}

/**
 * Usage Examples:
 * 
 * 1. Display status text:
 *    echo getStatusDisplayText($patient['status']);
 *    // "Disapproved" from DB displays as "Declined"
 * 
 * 2. Display status badge:
 *    echo getStatusBadge($patient['status']);
 *    // Outputs: <span class="...">❌ Declined</span>
 * 
 * 3. Generate dropdown options:
 *    <select name="status">
 *        <?php echo getStatusDropdownOptions($patient['status']); ?>
 *    </select>
 * 
 * 4. Generate dropdown with icons:
 *    <select name="status">
 *        <?php echo getStatusDropdownOptions($patient['status'], true); ?>
 *    </select>
 */
?>
