<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dropdown Test</title>
    
    <!-- Bootstrap CSS (as backup) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- MDB5 CSS -->
    <link rel="stylesheet" href="MDB5-STANDARD-UI-KIT-Free-9.2.0/css/mdb.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .test-dropdown {
            margin: 50px;
            padding: 20px;
            border: 2px solid #007bff;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="test-dropdown">
            <h2>Dropdown Test</h2>
            <p>Testing different dropdown implementations:</p>
            
            <!-- Bootstrap Dropdown -->
            <div class="dropdown mb-3">
                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Bootstrap Dropdown
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#">Profile</a></li>
                    <li><a class="dropdown-item" href="#">Orders</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#">Logout</a></li>
                </ul>
            </div>
            
            <!-- MDB5 Dropdown -->
            <div class="dropdown mb-3">
                <a class="btn btn-success dropdown-toggle" href="#" data-mdb-toggle="dropdown">
                    MDB5 Dropdown
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#">Profile</a></li>
                    <li><a class="dropdown-item" href="#">Orders</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#">Logout</a></li>
                </ul>
            </div>
            
            <!-- Manual Dropdown -->
            <div class="dropdown mb-3">
                <button class="btn btn-warning" onclick="toggleManualDropdown()">
                    Manual Dropdown
                </button>
                <ul class="dropdown-menu" id="manualDropdown" style="display: none;">
                    <li><a class="dropdown-item" href="#">Profile</a></li>
                    <li><a class="dropdown-item" href="#">Orders</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- MDB5 JS -->
    <script src="MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        // Manual dropdown function
        function toggleManualDropdown() {
            const dropdown = document.getElementById('manualDropdown');
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
        
        // Test MDB5 loading
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            
            // Check if MDB5 is loaded
            if (typeof window.mdb !== 'undefined') {
                console.log('MDB5 loaded successfully');
                
                // Try to initialize MDB5
                try {
                    window.mdb.AutoInit();
                    console.log('MDB5 AutoInit executed');
                } catch (error) {
                    console.error('MDB5 AutoInit error:', error);
                }
                
                // Manual MDB5 dropdown initialization
                const mdbDropdowns = document.querySelectorAll('[data-mdb-toggle="dropdown"]');
                console.log('Found MDB5 dropdowns:', mdbDropdowns.length);
                
                mdbDropdowns.forEach(function(element, index) {
                    try {
                        new window.mdb.Dropdown(element);
                        console.log('MDB5 dropdown', index, 'initialized');
                    } catch (error) {
                        console.error('MDB5 dropdown', index, 'error:', error);
                    }
                });
            } else {
                console.error('MDB5 not loaded');
            }
            
            // Check if Bootstrap is loaded
            if (typeof bootstrap !== 'undefined') {
                console.log('Bootstrap loaded successfully');
            } else {
                console.error('Bootstrap not loaded');
            }
        });
        
        // Close manual dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const manualDropdown = document.getElementById('manualDropdown');
            const button = event.target.closest('button');
            
            if (!manualDropdown.contains(event.target) && (!button || button.textContent !== 'Manual Dropdown')) {
                manualDropdown.style.display = 'none';
            }
        });
    </script>
</body>
</html>
