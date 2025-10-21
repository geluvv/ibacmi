<?php
// Function to get recent activities
function getRecentActivities($conn) {
    $activities = array();

    // Query to get recent activities
    $sql = "SELECT * FROM activities ORDER BY timestamp DESC LIMIT 5";
    $result = $conn->query($sql);

    // Check if the table exists or if there are records
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
    } else {
        // Fallback to mock data if no activities table or no records
        $activities = array(
            array(
                "action" => "New Student Record Added",
                "description" => "Added new student record for Juan Dela Cruz (ID: 2023-0001)",
                "timestamp" => date("Y-m-d H:i:s")
            ),
            array(
                "action" => "Document Updated",
                "description" => "Updated transcript records for Maria Santos (ID: 2022-0015)",
                "timestamp" => date("Y-m-d H:i:s", strtotime("-1 day"))
            ),
            array(
                "action" => "Document Flagged",
                "description" => "Flagged missing birth certificate for Pedro Reyes (ID: 2023-0056)",
                "timestamp" => date("Y-m-d H:i:s", strtotime("-2 days"))
            )
        );
    }

    return $activities;
}