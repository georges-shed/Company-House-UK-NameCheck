<?php
include 'reserved.php'; // Include the reserved keywords file

// Function to remove common suffixes
function removeSuffixes($name) {
    $ignoreSuffixes = array(
        " LTD", " CO", " UK", " PCL", " LIMITED", " PLC", " LLP", " GROUP", 
        " INTERNATIONAL", " SERVICES", " HOLDINGS", " CORPORATION", " CORP", 
        " INC", " LLC", " PARTNERSHIP", " AND CO", " & CO", " AND COMPANY", 
        " & COMPANY", " TRUST", " ASSOCIATES", " ASSOCIATION", " CHAMBERS", 
        " FOUNDATION", " FUND", " INSTITUTE", " SOCIETY", " UNION", " SYNDICATE", 
        " GMBH", " AG", " KG", " OHG", " e.V.", " gGmbH", " K.K.", " S.A.", 
        " S.P.A.", " S.L.", " B.V.", " N.V.", " S.A.R.L.", " OY", " AB"
    ); // Add other suffixes as needed
    return preg_replace('/\b(' . implode('|', $ignoreSuffixes) . ')\b/i', '', $name);
}

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $searchQuery = $_POST['search']; // Get search query from form input
} else {
    $searchQuery = ''; // Default search query is empty
}

$apiKey = 'd9023789-4c98-4333-81dc-de7bd3c7b526'; // Replace with your actual API key

// Container for response and other text
$responseText = '';
$showSearchForm = true; // Flag to show or hide the search form

// Check if the search query is a reserved keyword or contains a reserved phrase
$reservedResponse = isReservedKeyword($searchQuery);
$reservedPhraseResponse = containsReservedPhrase($searchQuery);

if ($reservedResponse) {
    $responseText .= '<div class="response-box reserved" style="background-color: #f39800; color: white; padding: 20px; margin-top: 20px; margin-bottom:10px; border-radius: 5px; text-align: center;">';
    $responseText .= '<img src="images/remove.png" alt="Error" style="width: 50px; height: 50px; margin-bottom:5px;">';
    $responseText .= '<h2 style="margin: 0;">' . htmlspecialchars($searchQuery) . '</h2>';
    $responseText .= '<p>' . htmlspecialchars($reservedResponse) . '</p>';
    $responseText .= '</div>';
} else if ($reservedPhraseResponse) {
    $responseText .= '<div class="response-box reserved" style="background-color: #f39800; color: white; padding: 20px; margin-top: 20px; margin-bottom:10px; border-radius: 5px; text-align: center;">';
    $responseText .= '<img src="images/checklist.png" alt="Error" style="width: 50px; height: 50px; margin-bottom:5px;">';
    $responseText .= '<h2 style="margin: 0;">' . htmlspecialchars($searchQuery) . '</h2>';
    $responseText .= '<p>' . $reservedPhraseResponse . '</p>'; // No need for htmlspecialchars here since we want to render HTML
    $responseText .= '</div>';
} else if (!empty($searchQuery)) {
    // Prepare the search query without suffixes
    $searchQueryWithoutSuffix = removeSuffixes($searchQuery);

    // Construct the API URL
    $apiUrl = "https://api.companieshouse.gov.uk/search/companies?q=" . urlencode($searchQueryWithoutSuffix);

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Basic ' . base64_encode($apiKey . ':')
    ));

    // Execute cURL session
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Check for errors and handle response
    if ($httpCode == 200) {
        // Success
        $responseData = json_decode($response, true);

        // Check if the exact company name (case insensitive) exists in the response
        $companyExists = false;
        foreach ($responseData['items'] as $item) {
            // Prepare the company name from API response without suffixes
            $itemTitleWithoutSuffix = removeSuffixes($item['title']);

            // Compare without considering suffixes
            if (strcasecmp($itemTitleWithoutSuffix, $searchQueryWithoutSuffix) === 0) {
                $companyExists = true;
                break;
            }
        }

        // Prepare response text based on whether company name exists
        if ($companyExists) {
            $responseText .= '<div class="response-box" style="background-color: #ff4f4f; color: white; padding: 20px; margin-top: 20px; border-radius: 5px; text-align: center;">';
            $responseText .= '<img src="images/remove.png" alt="Error" style="width: 50px; height: 50px; margin-bottom:5px;">';
            $responseText .= '<h2 style="margin: 0;">' . htmlspecialchars($searchQuery) . '</h2>';
            $responseText .= '<p>Unfortunately, this name is not available for registration. Please select another.</p>';
            $responseText .= '</div>';
        } else {
            $responseText .= '<div class="response-box" style="background-color: #28a745; color: white; padding: 20px; margin-top: 20px; border-radius: 5px; text-align: center;">';
            $responseText .= '<img src="images/success-icon.png" alt="Success" style="width: 50px; height: 50px; margin-bottom:5px;">';
            $responseText .= '<h2 style="margin: 0;">' . htmlspecialchars($searchQuery) . '</h2>';
            $responseText .= '<p>Congratulations! This company name is available.</p>';
            $responseText .= '</div>';
            $responseText .= '<div style="text-align: center; margin-top: 20px;">';
            $responseText .= '<button style="padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">Choose a Package</button>';
            $responseText .= '<br><a href="' . $_SERVER['PHP_SELF'] . '" style="margin-top: 10px; display: inline-block; color: #007bff;">Or search again</a>';
            $showSearchForm = false; // Hide the search form
        }
    } elseif ($httpCode == 401) {
        // Unauthorized
        $responseText .= '<div class="response-box" style="background-color: #ff4f4f; color: white; padding: 20px; margin-top: 20px; border-radius: 5px; text-align: center;">';
        $responseText .= '<img src="error-icon.png" alt="Error" style="width: 50px; height: 50px;">';
        $responseText .= '<p>API Key does not have access permission. HTTP Code 401 Unauthorized.</p>';
        $responseText .= '</div>';
    } else {
        // Other HTTP errors
        $responseText .= '<div class="response-box" style="background-color: #ff4f4f; color: white; padding: 20px; margin-top: 20px; border-radius: 5px; text-align: center;">';
        $responseText .= '<img src="error-icon.png" alt="Error" style="width: 50px; height: 50px;">';
        $responseText .= '<p>Error: HTTP Code ' . $httpCode . '</p>';
        $responseText .= '<p>Response:<br>' . htmlspecialchars($response) . '</p>';
        $responseText .= '</div>';
    }

    // Close cURL session
    curl_close($ch);
}
?>

<!-- HTML structure -->
<div class="container" style="max-width: 600px; margin: auto; padding: 20px;">
    <?php echo $responseText; ?>
    <?php if ($showSearchForm): ?>
    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="searchForm" style="text-align: center;">
        <input type="text" id="search" name="search" placeholder="Find your perfect company name" value="<?php echo htmlspecialchars($searchQuery); ?>" style="width: 80%; padding: 10px; margin-bottom: 10px; border-radius: 5px; border: 1px solid #ccc;">
        <button type="submit" style="padding: 10px 20px; background-color: #f39800; color: white; border: none; border-radius: 5px; cursor: pointer;">Search</button>
    </form>
    <?php endif; ?>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>
$(document).ready(function() {
    $('#searchForm').submit(function(e) {
        e.preventDefault(); // Prevent default form submission
        var searchQuery = $('#search').val(); // Get the search query

        $.ajax({
            type: "POST",
            url: "<?php echo $_SERVER['PHP_SELF']; ?>", // Your PHP file that handles the search
            data: { search: searchQuery },
            success: function(response) {
                $('.container').html(response); // Display the response
            }
        });
    });
});
</script>
