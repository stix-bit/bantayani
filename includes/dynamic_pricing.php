<?php
/**
 * Dynamic pricing for price benchmarking.
 * Calculates recommended price and allowed range based on:
 * - Supply (total quantity currently listed)
 * - Demand (quantity sold in completed orders)
 * - Current listings (average price of active inventory)
 * - Historical average (from completed orders)
 *
 * Sellers must set price within the allowed range.
 */

/** Completed order statuses used for demand/historical metrics */
define('DYNAMIC_PRICING_COMPLETED_STATUS', ["Delivered", "Shipped"]);

/** Default lookback days for historical and demand */
define('DYNAMIC_PRICING_DEFAULT_DAYS', 90);

/** Allowed deviation from recommended (e.g. 0.15 = ±15%) */
define('DYNAMIC_PRICING_RANGE_PCT', 0.15);

/** Weight: historical average vs current listing average (0–1 = fraction for historical) */
define('DYNAMIC_PRICING_HISTORICAL_WEIGHT', 0.6);

/** Demand/supply adjustment: max nudge to recommended (e.g. 0.05 = ±5%) */
define('DYNAMIC_PRICING_DEMAND_SUPPLY_NUDGE', 0.05);

/**
 * Get dynamic price metrics for a crop.
 *
 * @param mysqli $conn Database connection
 * @param int $crop_id Crop ID
 * @param int $days Lookback days for historical/demand (default 90)
 * @return array Keys: recommended, price_min, price_max, supply, demand, listing_avg, historical_avg, historical_min, historical_max, has_data
 */
function getDynamicPriceRange($conn, $crop_id, $days = DYNAMIC_PRICING_DEFAULT_DAYS) {
    $crop_id = (int) $crop_id;
    $days = (int) $days;
    $out = [
        'recommended' => null,
        'price_min' => null,
        'price_max' => null,
        'supply' => 0,
        'demand' => 0,
        'listing_avg' => null,
        'historical_avg' => null,
        'historical_min' => null,
        'historical_max' => null,
        'has_data' => false,
    ];

    // Supply: total quantity listed for this crop
    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) AS supply FROM crops_inventory WHERE crop_id = ?");
    $stmt->bind_param("i", $crop_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $out['supply'] = (float) $row['supply'];
    }

    // Current listings: average price for this crop (only confirmed harvest entries)
    $stmt = $conn->prepare("SELECT AVG(price) AS listing_avg, MIN(price) AS listing_min, MAX(price) AS listing_max
        FROM crops_inventory
        WHERE crop_id = ?
          AND price IS NOT NULL
          AND price > 0
          AND harvest_status = 'Confirmed'");
    $stmt->bind_param("i", $crop_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && $row['listing_avg'] !== null) {
        $out['listing_avg'] = (float) $row['listing_avg'];
    }

    // Demand + historical: from completed orders (order_items with inventory_id)
    $statuses = DYNAMIC_PRICING_COMPLETED_STATUS;
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $sql = "SELECT
                COALESCE(SUM(oi.quantity), 0) AS demand,
                AVG(ci.price) AS historical_avg,
                MIN(ci.price) AS historical_min,
                MAX(ci.price) AS historical_max
            FROM order_items oi
            INNER JOIN crops_inventory ci ON oi.inventory_id = ci.inventory_id AND ci.crop_id = ?
            INNER JOIN orders o ON oi.order_id = o.order_id
            WHERE o.order_status IN ($placeholders)
            AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            AND oi.inventory_id IS NOT NULL";
    $stmt = $conn->prepare($sql);
    $types = 'i' . str_repeat('s', count($statuses)) . 'i';
    $params = array_merge([$crop_id], $statuses, [$days]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $out['demand'] = (float) $row['demand'];
        if ($row['historical_avg'] !== null) {
            $out['historical_avg'] = (float) $row['historical_avg'];
            $out['historical_min'] = $row['historical_min'] !== null ? (float) $row['historical_min'] : null;
            $out['historical_max'] = $row['historical_max'] !== null ? (float) $row['historical_max'] : null;
        }
    }

    // Compute recommended and range
    $hist = $out['historical_avg'];
    $list = $out['listing_avg'];
    $base = null;
    if ($hist !== null && $list !== null) {
        $w = DYNAMIC_PRICING_HISTORICAL_WEIGHT;
        $base = $w * $hist + (1 - $w) * $list;
    } elseif ($hist !== null) {
        $base = $hist;
    } elseif ($list !== null) {
        $base = $list;
    }

    if ($base === null || $base <= 0) {
        $out['has_data'] = false;
        return $out;
    }

    // Demand/supply nudge: if demand > supply, nudge up; if supply > demand, nudge down
    $nudge = 0;
    if ($out['supply'] > 0) {
        $ratio = $out['demand'] / $out['supply'];
        if ($ratio > 1.2) {
            $nudge = DYNAMIC_PRICING_DEMAND_SUPPLY_NUDGE;
        } elseif ($ratio < 0.8) {
            $nudge = -DYNAMIC_PRICING_DEMAND_SUPPLY_NUDGE;
        }
    }
    $out['recommended'] = round($base * (1 + $nudge), 2);
    $out['recommended'] = max(0.01, $out['recommended']);

    // Allowed range: ±RANGE_PCT, clamped by historical min/max when available
    $range = $out['recommended'] * DYNAMIC_PRICING_RANGE_PCT;
    $out['price_min'] = max(0.01, $out['recommended'] - $range);
    $out['price_max'] = $out['recommended'] + $range;
    if ($out['historical_min'] !== null) {
        $out['price_min'] = max($out['price_min'], $out['historical_min'] * 0.5);
    }
    if ($out['historical_max'] !== null) {
        $out['price_max'] = min($out['price_max'], $out['historical_max'] * 1.5);
    }
    $out['price_min'] = round($out['price_min'], 2);
    $out['price_max'] = round($out['price_max'], 2);
    $out['has_data'] = true;

    return $out;
}

/**
 * Check if a seller price is within the allowed dynamic range.
 *
 * @param mysqli $conn Database connection
 * @param int $crop_id Crop ID
 * @param float $price Price to validate
 * @param int $days Lookback days
 * @return array ['valid' => bool, 'message' => string, 'range' => array from getDynamicPriceRange]
 */
function validatePriceInRange($conn, $crop_id, $price, $days = DYNAMIC_PRICING_DEFAULT_DAYS) {
    $range = getDynamicPriceRange($conn, $crop_id, $days);
    $price = (float) $price;

    if (!$range['has_data']) {
        return [
            'valid' => true,
            'message' => 'No market data yet; price is accepted. Consider setting a competitive price.',
            'range' => $range,
        ];
    }

    if ($price < $range['price_min']) {
        return [
            'valid' => false,
            'message' => sprintf('Price must be at least ₱%s (recommended: ₱%s). Min allowed: ₱%s.', number_format($range['price_min'], 2), number_format($range['recommended'], 2), number_format($range['price_min'], 2)),
            'range' => $range,
        ];
    }
    if ($price > $range['price_max']) {
        return [
            'valid' => false,
            'message' => sprintf('Price must be at most ₱%s (recommended: ₱%s). Max allowed: ₱%s.', number_format($range['price_max'], 2), number_format($range['recommended'], 2), number_format($range['price_max'], 2)),
            'range' => $range,
        ];
    }

    return [
        'valid' => true,
        'message' => '',
        'range' => $range,
    ];
}

/**
 * Get dynamic price range for all crops (e.g. for benchmarking or dropdowns).
 *
 * @param mysqli $conn Database connection
 * @param int $days Lookback days
 * @return array [crop_id => getDynamicPriceRange(...), ...]
 */
function getAllCropsDynamicPriceRanges($conn, $days = DYNAMIC_PRICING_DEFAULT_DAYS) {
    $stmt = $conn->query("SELECT crop_id FROM crops ORDER BY crop_id");
    if (!$stmt) return [];
    $out = [];
    while ($row = $stmt->fetch_assoc()) {
        $out[(int) $row['crop_id']] = getDynamicPriceRange($conn, (int) $row['crop_id'], $days);
    }
    $stmt->close();
    return $out;
}
