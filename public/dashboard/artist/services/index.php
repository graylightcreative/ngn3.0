<?php
// Include necessary NGN bootstrap and layout files
require_once __DIR__ . '/../../../lib/bootstrap.php';

// Define page title
$pageTitle = 'Professional Audio Services';
?>

<?php // START: Content specific to the services marketplace page ?>
<div class="container mx-auto p-6">
    <h1 class="text-4xl font-bold mb-8 text-center sk-text-gradient-primary sk-text-glow">Professional Audio Services</h1>

    <div class="sk-grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <?php // Service Card 1: AI Mix Analysis ?>
        <div class="sk-card sk-card-hover sk-card-glow p-6 flex flex-col justify-between h-full">
            <div>
                <h3 class="text-2xl font-semibold mb-3 sk-text-gradient-secondary">AI Mix Analysis</h3>
                <p class="text-lg mb-4">Get intelligent feedback on your mix to improve clarity, balance, and impact.</p>
                <p class="text-xl font-bold mb-6">
                    <span class="sk-text-spark">15 Sparks</span> / <span class="text-gray-400">Free for Investors</span>
                </p>
            </div>
            <div>
                <a href="#" data-service="AI Mix Analysis" data-price="15" data-currency="sparks" class="sk-btn sk-btn-primary sk-btn-glow w-full text-center analyze-mix-btn">Analyze My Mix</a>
            </div>
        </div>

        <?php // Service Card 2: Professional Mastering ?>
        <div class="sk-card sk-card-hover sk-card-glow p-6 flex flex-col justify-between h-full">
            <div>
                <h3 class="text-2xl font-semibold mb-3 sk-text-gradient-secondary">Professional Mastering</h3>
                <p class="text-lg mb-4">Industry-standard mastering to give your tracks a polished, radio-ready sound.</p>
                <p class="text-xl font-bold mb-6">$50</p>
            </div>
            <div>
                <button data-service="Professional Mastering" data-price="50" data-currency="usd" class="sk-btn sk-btn-secondary sk-btn-glow w-full text-center order-service-btn">Order Now</button>
            </div>
        </div>

        <?php // Service Card 3: Radio Promo Campaign ?>
        <div class="sk-card sk-card-hover sk-card-glow p-6 flex flex-col justify-between h-full">
            <div>
                <h3 class="text-2xl font-semibold mb-3 sk-text-gradient-secondary">Radio Promo Campaign</h3>
                <p class="text-lg mb-4">Reach new audiences with targeted radio promotion to stations and curators.</p>
                <p class="text-xl font-bold mb-6">$250</p>
            </div>
            <div>
                <button data-service="Radio Promo Campaign" data-price="250" data-currency="usd" class="sk-btn sk-btn-secondary sk-btn-glow w-full text-center order-service-btn">Start Campaign</button>
            </div>
        </div>
    </div>
</div>
<?php // END: Content specific to the services marketplace page ?>

<?php // Include Axios for AJAX requests. Ensure Axios is available in your project. ?>
<?php // If not globally available, consider including it here: ?>
<?php // <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script> ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handler for ordering services
        const orderServiceButtons = document.querySelectorAll('.order-service-btn');
        orderServiceButtons.forEach(button => {
            button.addEventListener('click', function() {
                const serviceType = this.dataset.service;
                const price = this.dataset.price;
                // Note: The AI Mix Analysis uses 'Sparks', which requires special handling.
                // For now, we'll send a generic request. A real implementation would need to check currency.
                if (serviceType === 'AI Mix Analysis') {
                    alert('AI Mix Analysis requires Sparks. Please check your Spark balance or contact support for purchasing options.');
                    // In a real scenario, this would trigger a different flow for Spark redemption.
                    return;
                }

                // Make the API call using Axios (ensure Axios is loaded)
                if (typeof axios !== 'undefined') {
                    axios.post('/api/v1/services/order', {
                        service_type: serviceType,
                        // price: price // Price is handled server-side based on service_type
                    })
                    .then(function (response) {
                        // Handle success
                        if (response.data.success) {
                            alert('Order Received! We will contact you shortly.');
                            // Optionally, disable the button or change its text
                            button.textContent = 'Order Placed';
                            button.disabled = true;
                        } else {
                            // Handle API errors
                            alert('Error: ' + (response.data.message || 'Failed to place order.'));
                        }
                    })
                    .catch(function (error) {
                        // Handle network or request errors
                        console.error('Order submission error:', error);
                        alert('An error occurred. Please try again later.');
                    });
                } else {
                    console.error('Axios is not loaded. Cannot submit order.');
                    alert('Error: The ordering system is currently unavailable.');
                }
            });
        });

        // Handler for AI Mix Analysis (special case)
        const analyzeMixButton = document.querySelector('.analyze-mix-btn');
        if (analyzeMixButton) {
            analyzeMixButton.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default link behavior
                alert('AI Mix Analysis requires Sparks. Please check your Spark balance or contact support for purchasing options.');
                // Redirect to a Spark balance/purchase page if available, or show more info.
                // window.location.href = '/dashboard/sparks'; 
            });
        }
    });
</script>