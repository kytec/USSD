$(document).ready(function() {
    // Store the current session
    let currentSession = '';
    let currentPhone = '0200000000'; // Default test phone
    
    // Handle form submission
    $("#ussd-form").on("submit", function(e) {
        e.preventDefault();
        
        const input = $("#ussd-input").val().trim();
        
        if (input === '') {
            return;
        }
        
        // Show loading indicator
        $("#ussd-display").html("Processing...");
        
        // Send AJAX request
        $.ajax({
            type: "POST",
            url: "process.php",
            data: { 
                ussd_input: input,
                phone: currentPhone  // Add the phone number to the request
            },
            success: function(response) {
                // Update display with response
                $("#ussd-display").html(response);
                
                // Clear input field
                $("#ussd-input").val('');
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                console.log(xhr.responseText);
                $("#ussd-display").html("Error processing request. Please try again.");
            }
        });
    });
    
    // Handle clear button
    $("#ussd-clear").on("click", function() {
        // Reset session by sending cancel action
        $.ajax({
            type: "POST",
            url: "process.php",
            data: { 
                action: 'cancel'
            },
            success: function(response) {
                // Update display with response
                $("#ussd-display").html(response);
                
                // Clear input field
                $("#ussd-input").val('');
            }
        });
    });
    
    // Handle keypad buttons
    $(".keypad-btn").on("click", function() {
        const key = $(this).data("key");
        const input = $("#ussd-input");
        input.val(input.val() + key);
        input.focus();
    });
    
    // Quick dial button
    $("#quick-dial").on("click", function() {
        $("#ussd-input").val("*123#");
        $("#ussd-form").submit();
    });
    
    // Initialize the simulator
    function initSimulator() {
        // Reset the session on page load
        $.ajax({
            type: "POST",
            url: "process.php",
            data: { 
                action: 'cancel'
            },
            success: function(response) {
                // Update display with response
                $("#ussd-display").html(response);
            }
        });
    }
    
    // Start the simulator
    initSimulator();
});