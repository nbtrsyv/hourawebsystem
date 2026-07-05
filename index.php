<!-- index.php -->
<?php 
require_once 'includes/session_start.php';
$is_logged_in = isset($_SESSION['user_id']);

// Get real statistics from database (matching admin dashboard)
$total_users = 0;
$total_hours = 0;
$avg_rating = 0;
$total_services = 0;
$active_players = 0;
$total_hours_traded = 0;
$most_earned_badge = null;
$popular_mission = null;
$tutoring_count = 0;
$repair_count = 0;
$tech_count = 0;
$cleaning_count = 0;
$pet_count = 0;
$creative_count = 0;

try {
    // Count total users (same as admin dashboard)
    $total_users = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn() ?? 0;
    
    // Count total services (same as admin dashboard)
    $total_services = $conn->query("SELECT COUNT(*) FROM services")->fetchColumn() ?? 0;
    
    // Sum total hours from users time_balance (same as admin dashboard)
    $total_hours = (int)($conn->query("SELECT SUM(time_balance) FROM users")->fetchColumn() ?? 0);
    
    // Get average rating
    $stmt = $conn->prepare("SELECT AVG(rating) as avg FROM reviews WHERE rating > 0");
    $stmt->execute();
    $result = $stmt->fetch();
    $avg_rating = round($result['avg'] ?? 0, 1);
    
    // Active players = users who have completed at least one service request
    $active_players = $conn->query("SELECT COUNT(DISTINCT user_id) FROM service_requests WHERE status = 'completed'")->fetchColumn() ?? 0;
    
    // Total hours traded = sum of spent hours from time_transactions (same metric as admin)
    $total_hours_traded = (int)($conn->query("SELECT SUM(hours) FROM time_transactions WHERE transaction_type = 'spend'")->fetchColumn() ?? 0);
    
    // Get most earned badge this week
    $most_earned_badge = $conn->query("
        SELECT b.badge_name, b.emoji, COUNT(ub.user_badge_id) as count
        FROM badges b
        LEFT JOIN user_badges ub ON b.badge_id = b.badge_id
        WHERE ub.earned_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY b.badge_id
        ORDER BY count DESC
        LIMIT 1
    ")->fetch();
    
    // Get most completed mission
    $popular_mission = $conn->query("
        SELECT m.mission_name, m.emoji, COUNT(um.user_mission_id) as count
        FROM missions m
        LEFT JOIN user_missions um ON m.mission_id = m.mission_id
        WHERE um.status = 'completed'
        GROUP BY m.mission_id
        ORDER BY count DESC
        LIMIT 1
    ")->fetch();
    
    // Get service counts by category
    $tutoring_count = $conn->query("
        SELECT COUNT(*) FROM services s
        LEFT JOIN categories c ON s.category_id = c.category_id
        WHERE c.name LIKE '%tutor%' OR c.name LIKE '%education%' OR c.name LIKE '%academic%'
    ")->fetchColumn() ?? 0;
    
    $repair_count = $conn->query("
        SELECT COUNT(*) FROM services s
        LEFT JOIN categories c ON s.category_id = c.category_id
        WHERE c.name LIKE '%repair%' OR c.name LIKE '%home%' OR c.name LIKE '%furniture%'
    ")->fetchColumn() ?? 0;
    
    $tech_count = $conn->query("
        SELECT COUNT(*) FROM services s
        LEFT JOIN categories c ON s.category_id = c.category_id
        WHERE c.name LIKE '%tech%' OR c.name LIKE '%computer%' OR c.name LIKE '%digital%'
    ")->fetchColumn() ?? 0;
    
    $cleaning_count = $conn->query("
        SELECT COUNT(*) FROM services s
        LEFT JOIN categories c ON s.category_id = c.category_id
        WHERE c.name LIKE '%clean%' OR c.name LIKE '%organization%'
    ")->fetchColumn() ?? 0;
    
    $pet_count = $conn->query("
        SELECT COUNT(*) FROM services s
        LEFT JOIN categories c ON s.category_id = c.category_id
        WHERE c.name LIKE '%pet%' OR c.name LIKE '%animal%' OR c.name LIKE '%dog%'
    ")->fetchColumn() ?? 0;
    
    $creative_count = $conn->query("
        SELECT COUNT(*) FROM services s
        LEFT JOIN categories c ON s.category_id = c.category_id
        WHERE c.name LIKE '%creative%' OR c.name LIKE '%art%' OR c.name LIKE '%design%' OR c.name LIKE '%photo%' OR c.name LIKE '%music%'
    ")->fetchColumn() ?? 0;
    
} catch(Exception $e) {
    // Fallback to zero if query fails
    error_log("Stats query error: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<?php if(!$is_logged_in): ?>
<!-- ====================== -->
<!-- GUEST VIEW - HOMEPAGE -->
<!-- ====================== -->

<!-- Hero Section dengan ID untuk navigation -->
<section id="home" class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3">
                    Exchange Skills, Build Community
                </h1>
                <p class="lead mb-4">
                    Trade skills without money. Build your community. Get help when you need it.
                </p>
                <div class="mb-4">
                    <a href="register.php" class="btn btn-primary btn-lg me-3 mb-2">
                        <i class="bi bi-person-plus me-2"></i>Join Free Today
                    </a>
                    <a href="#why-join" class="btn btn-outline-primary btn-lg mb-2">
                        Learn More <i class="bi bi-arrow-down ms-2"></i>
                    </a>
                </div>
                <div class="mt-4">
                    <p class="text-muted mb-2">✨ Join our community members</p>
                    <p class="text-muted">🎁 Get 2 FREE hours when you sign up</p>
                </div>
            </div>

            <!-- Community Stats Card -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-lg h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%); color: white; overflow: hidden;">
                    <div style="position: absolute; top: -30px; right: -30px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                    <div style="position: absolute; bottom: -20px; left: -20px; width: 120px; height: 120px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
                    
                    <div class="card-body p-5" style="position: relative; z-index: 1;">
                        <h3 class="mb-4" style="font-weight: 900;">🌟 Join Today</h3>
                        
                        <!-- Stats -->
                        <div class="row g-3 mb-5">
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 10px; text-align: center;">
                                    <div style="font-size: 1.8rem; font-weight: 800;"><?php echo $total_users; ?></div>
                                    <small style="opacity: 0.9;">Members</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 10px; text-align: center;">
                                    <div style="font-size: 1.8rem; font-weight: 800;"><?php echo $total_services; ?></div>
                                    <small style="opacity: 0.9;">Services</small>
                                </div>
                            </div>
                        </div>

                        <!-- Benefits List -->
                        <div class="mb-4">
                            <div class="mb-3">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="background: rgba(255,255,255,0.3); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">✓</div>
                                    <div>
                                        <strong>No Payment Required</strong>
                                        <small style="opacity: 0.9; display: block;">Trade hours, not cash</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="background: rgba(255,255,255,0.3); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">✓</div>
                                    <div>
                                        <strong>Verified Members</strong>
                                        <small style="opacity: 0.9; display: block;">Rated & reviewed</small>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="background: rgba(255,255,255,0.3); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">✓</div>
                                    <div>
                                        <strong>24/7 Support</strong>
                                        <small style="opacity: 0.9; display: block;">Community help anytime</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <a href="register.php" class="btn btn-light btn-lg w-100" style="color: #667eea; font-weight: 700;">
                            Get Started Free 🚀
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Join Houra? Section -->
<section id="why-join" class="py-5" style="background: linear-gradient(135deg, #f0f8ff 0%, rgba(230, 230, 250, 0.5) 100%);">
    <div class="container">
        <h2 class="text-center mb-5" style="color: var(--dark-purple); font-weight: 800; font-size: 2.5rem;">
            <i class="bi bi-star-fill me-2"></i>Why Join Houra?
        </h2>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-lg">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <div style="font-size: 3rem; margin-bottom: 15px;">💰</div>
                        </div>
                        <h5 style="color: var(--dark-purple); font-weight: 700;">No Money Needed</h5>
                        <p class="card-text">
                            Trade services using time credits instead of cash. 
                            Everyone's time is valued equally.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-lg">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <div style="font-size: 3rem; margin-bottom: 15px;">👥</div>
                        </div>
                        <h5 style="color: var(--dark-purple); font-weight: 700;">Build Community</h5>
                        <p class="card-text">
                            Connect with neighbors, share skills, and build 
                            meaningful relationships in your local area.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-lg">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <div style="font-size: 3rem; margin-bottom: 15px;">🛡️</div>
                        </div>
                        <h5 style="color: var(--dark-purple); font-weight: 700;">Safe & Verified</h5>
                        <p class="card-text">
                            User ratings, reviews, and photo proof system 
                            ensure safe and reliable exchanges.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section dengan smooth scroll target -->
<section id="how-it-works" class="py-5" style="background-color: var(--light-bg);">
    <div class="container">
        <h2 class="text-center mb-5" style="color: var(--dark-purple); font-weight: 800; font-size: 2.5rem;">
            <i class="bi bi-diagram-3 me-2"></i>How It Works
        </h2>
        
        <div class="row g-4 align-items-center">
            <!-- Timeline -->
            <div class="col-lg-8">
                <div class="row g-4">
                    <!-- Step 1 -->
                    <div class="col-md-6">
                        <div class="card h-100 text-center border-0 shadow-lg" style="border-top: 4px solid var(--primary-purple);">
                            <div class="card-body p-4">
                                <div class="step-number mb-3">
                                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center" 
                                          style="width: 70px; height: 70px; background: linear-gradient(135deg, var(--primary-purple), var(--dark-purple)); color: white; font-size: 2rem; font-weight: bold;">
                                        1
                                    </span>
                                </div>
                                <h5 style="color: var(--dark-purple); font-weight: 700;">Sign Up Free</h5>
                                <p>Create account, get 2 bonus hours instantly</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="col-md-6">
                        <div class="card h-100 text-center border-0 shadow-lg" style="border-top: 4px solid var(--primary-purple);">
                            <div class="card-body p-4">
                                <div class="step-number mb-3">
                                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center" 
                                          style="width: 70px; height: 70px; background: linear-gradient(135deg, var(--primary-purple), var(--dark-purple)); color: white; font-size: 2rem; font-weight: bold;">
                                        2
                                    </span>
                                </div>
                                <h5 style="color: var(--dark-purple); font-weight: 700;">Offer Skills</h5>
                                <p>List what you can do: tutor, clean, repair, etc</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="col-md-6">
                        <div class="card h-100 text-center border-0 shadow-lg" style="border-top: 4px solid var(--teal-accent);">
                            <div class="card-body p-4">
                                <div class="step-number mb-3">
                                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center" 
                                          style="width: 70px; height: 70px; background: linear-gradient(135deg, var(--teal-accent), #17a2a2); color: white; font-size: 2rem; font-weight: bold;">
                                        3
                                    </span>
                                </div>
                                <h5 style="color: var(--dark-purple); font-weight: 700;">Earn Time Credits</h5>
                                <p>Get 1 hour credit for every hour you help</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 4 -->
                    <div class="col-md-6">
                        <div class="card h-100 text-center border-0 shadow-lg" style="border-top: 4px solid var(--teal-accent);">
                            <div class="card-body p-4">
                                <div class="step-number mb-3">
                                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center" 
                                          style="width: 70px; height: 70px; background: linear-gradient(135deg, var(--teal-accent), #17a2a2); color: white; font-size: 2rem; font-weight: bold;">
                                        4
                                    </span>
                                </div>
                                <h5 style="color: var(--dark-purple); font-weight: 700;">Get Help</h5>
                                <p>Use credits to request services you need</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card h-100 border-0 shadow-lg" style="background: linear-gradient(135deg, var(--primary-purple), var(--dark-purple)); color: white;">
                    <div class="card-body p-5 text-center">
                        <h3 class="mb-4">Ready to Start?</h3>
                        <p class="mb-4">Join thousands of community members exchanging skills without money.</p>
                        <a href="register.php" class="btn btn-light btn-lg w-100">
                            <i class="bi bi-lightning-charge me-2"></i>Get Started Now
                        </a>
                        <p class="mt-4 small">✨ 2 FREE hours when you join</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Browse Services Preview -->
<section class="py-5" style="background: linear-gradient(135deg, var(--light-purple) 0%, rgba(32, 178, 170, 0.1) 100%);">
    <div class="container">
        <h2 class="text-center mb-5" style="color: var(--dark-purple); font-weight: 800; font-size: 2.5rem;">
            <i class="bi bi-search me-2"></i>Popular Services
        </h2>
        
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-lg overflow-hidden">
                    <img src="https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?w=400&h=200&fit=crop" alt="Academic Tutoring" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="card-title" style="color: var(--dark-purple); font-weight: 700;">Academic Tutoring</h5>
                        <p class="card-text">Math, Science, Languages, Test prep help from community experts.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-lg overflow-hidden">
                    <img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=200&fit=crop" alt="Home Repairs" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="card-title" style="color: var(--dark-purple); font-weight: 700;">Home Repairs</h5>
                        <p class="card-text">Furniture assembly, basic plumbing, electrical help, painting.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-lg overflow-hidden">
                    <img src="https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=400&h=200&fit=crop" alt="Tech Assistance" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="card-title" style="color: var(--dark-purple); font-weight: 700;">Tech Assistance</h5>
                        <p class="card-text">Computer setup, phone help, software installation, digital skills.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-lg overflow-hidden">
                    <img src="https://images.unsplash.com/photo-1552321554-5fefe8c9ef14?w=400&h=200&fit=crop" alt="Cleaning Services" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="card-title" style="color: var(--dark-purple); font-weight: 700;">Cleaning Services</h5>
                        <p class="card-text">Home cleaning, organizing, window washing, yard work.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-lg overflow-hidden">
                    <img src="https://www.dailypaws.com/thmb/_d5OeGSBFxXculBBLduw0P9QXIU=/750x0/filters:no_upscale():max_bytes(150000):strip_icc():format(webp)/facts-about-cats-1292117990-2000-6b1a096d6f6f4ea48deb166a89d4bdea.jpg" alt="Pet Care" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="card-title" style="color: var(--dark-purple); font-weight: 700;">Pet Care</h5>
                        <p class="card-text">Dog walking, pet sitting, grooming services, training help.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-lg overflow-hidden">
                    <img src="https://images.unsplash.com/photo-1561070791-2526d30994b5?w=400&h=200&fit=crop" alt="Creative Services" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="card-title" style="color: var(--dark-purple); font-weight: 700;">Creative Services</h5>
                        <p class="card-text">Photography, graphic design, music lessons, art classes.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center">
            <a href="services.php" class="btn btn-primary btn-lg">
                <i class="bi bi-search me-2"></i>Explore All 50+ Services
            </a>
        </div>
    </div>
</section>

<!-- Chatbot Section -->
<section class="py-5" style="background-color: var(--light-bg);">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h3 style="color: var(--dark-purple);">
                    <i class="bi bi-robot me-2"></i>Need Help? Ask HouraBot! (AI Powered)
                </h3>
                <p class="mb-4">Our AI assistant powered by Google Gemini can answer questions about Houra:</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> How to register & get started</li>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Understanding time credits system</li>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> How to offer & request services</li>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Safety guidelines & best practices</li>
                    <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Smart contextual conversations</li>
                </ul>
                <div class="mt-3">
                    <span class="badge bg-success">AI Powered</span>
                    <span class="badge bg-info">24/7 Available</span>
                    <span class="badge bg-warning text-dark">Free to Use</span>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-robot me-2"></i>HouraBot AI Assistant
                        </h5>
                        <span class="badge bg-light text-primary" id="ai-status">● Online</span>
                    </div>
                    <div class="card-body">
                        <!-- Chat Messages Area -->
                        <div id="chatbot-messages" style="height: 200px; overflow-y: auto; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                            <div class="bot-message mb-2">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="bi bi-robot text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="bg-light p-3 rounded">
                                            <strong>HouraBot AI:</strong> Hi! I'm your AI assistant powered by Google Gemini. I can help you with any questions about Houra - how to exchange skills, earn time credits, or use the platform. How can I assist you today? 😊
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Typing Indicator (Hidden by default) -->
                        <div id="typing-indicator" style="display: none; margin-bottom: 10px;">
                            <div class="d-flex align-items-center text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                <small>AI is thinking...</small>
                            </div>
                        </div>
                        
                        <!-- Chat Input -->
                        <div class="input-group">
                            <input type="text" class="form-control" id="chatbot-input" 
                                   placeholder="Ask me anything about Houra..." 
                                   onkeypress="if(event.keyCode==13) askAIChatbot()"
                                   maxlength="500">
                            <button class="btn btn-primary" onclick="askAIChatbot()" id="send-btn">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                        <small class="text-muted mt-1 d-block">Powered by Google Gemini AI • <span id="char-count">0</span>/500</small>
                        
                        <!-- Quick Questions -->
                        <div class="mt-3">
                            <small class="text-muted d-block mb-2">Try asking:</small>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-sm btn-outline-secondary" onclick="quickQuestionAI('How do I register?')">
                                    How to register?
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="quickQuestionAI('What is Time Wallet?')">
                                    What is Time Wallet?
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="quickQuestionAI('How to earn hours?')">
                                    How to earn hours?
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="quickQuestionAI('Is Houra safe?')">
                                    Is it safe?
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- JavaScript untuk AI Chatbot -->
<script>
// AI Chatbot State
var currentSessionId = null;
var isProcessing = false;

// Character counter
var chatInput = document.getElementById('chatbot-input');
if (chatInput) {
    chatInput.addEventListener('input', function() {
        var charCount = document.getElementById('char-count');
        if (charCount) {
            charCount.textContent = this.value.length;
        }
    });
}

function toggleAIChatbot() {
    // Cari seksyen chatbot di dalam halaman
    const chatbotSection = document.querySelector('.card.shadow .card-header').closest('section');
    
    if (chatbotSection) {
        // Skrol ke bahagian chatbot dengan kesan smooth
        window.scrollTo({
            top: chatbotSection.offsetTop - 80,
            behavior: 'smooth'
        });
        
        // Fokuskan terus ke input box supaya user boleh terus menaip
        setTimeout(() => {
            document.getElementById('chatbot-input').focus();
        }, 800);
    }
}

// Tambah kesan hover pada butang (opsional)
const floatBtn = document.getElementById('floating-chatbot');
if(floatBtn) {
    floatBtn.onmouseover = function() { this.style.transform = 'scale(1.1)'; };
    floatBtn.onmouseout = function() { this.style.transform = 'scale(1)'; };
}

function askAIChatbot() {
    var input = document.getElementById('chatbot-input');
    var message = input.value.trim();
    
    if (message === '' || isProcessing) return;
    
    // Add user message
    addMessageAI(message, 'user');
    input.value = '';
    var charCount = document.getElementById('char-count');
    if (charCount) {
        charCount.textContent = '0';
    }
    
    // Show typing indicator
    showTypingIndicator(true);
    isProcessing = true;
    document.getElementById('send-btn').disabled = true;
    
    // Call AI API
    fetch('chatbot_ai.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            message: message,
            session_id: currentSessionId
        })
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        showTypingIndicator(false);
        isProcessing = false;
        document.getElementById('send-btn').disabled = false;
        
        if (data.status === 'success') {
            // Save session ID for context
            if (data.session_id) {
                currentSessionId = data.session_id;
            }
            
            // Add AI response
            addMessageAI(data.response, 'bot', data.is_ai);
            
            // Update status badge if fallback
            if (data.is_fallback) {
                var aiStatus = document.getElementById('ai-status');
                if (aiStatus) {
                    aiStatus.className = 'badge bg-warning text-dark';
                    aiStatus.textContent = '● Fallback Mode';
                }
            }
        } else {
            // Error response
            addMessageAI('Sorry, I encountered an error: ' + data.message, 'bot', false, true);
        }
    })
    .catch(function(error) {
        showTypingIndicator(false);
        isProcessing = false;
        document.getElementById('send-btn').disabled = false;
        
        console.error('AI Chatbot Error:', error);
        addMessageAI("Sorry, I'm having trouble connecting right now. Please try again later.", 'bot', false, true);
    });
}

function quickQuestionAI(question) {
    var input = document.getElementById('chatbot-input');
    if (input) {
        input.value = question;
        askAIChatbot();
    }
}

function addMessageAI(text, sender, isAI, isError) {
    // Set default values
    if (typeof isAI === 'undefined') isAI = true;
    if (typeof isError === 'undefined') isError = false;
    
    var messagesDiv = document.getElementById('chatbot-messages');
    var messageDiv = document.createElement('div');
    messageDiv.className = sender + '-message mb-2';
    
    var icon = sender === 'user' ? 'bi-person' : 'bi-robot';
    var bgClass = sender === 'user' ? 'bg-primary text-white' : 'bg-light';
    var align = sender === 'user' ? 'justify-content-end' : '';
    
    // Style untuk error
    if (isError) {
        bgClass = 'bg-danger text-white';
    }
    
    // Add AI badge untuk bot messages
    var aiBadge = '';
    if (sender === 'bot' && isAI && !isError) {
        aiBadge = '<span class="badge bg-success ms-2" style="font-size: 0.7em;">AI</span>';
    }
    var errorBadge = '';
    if (isError) {
        errorBadge = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7em;">Error</span>';
    }
    
    var html = '<div class="d-flex ' + align + '">';
    if (sender === 'bot') {
        html += '<div class="flex-shrink-0"><i class="bi ' + icon + ' text-primary"></i>' + aiBadge + errorBadge + '</div>';
    }
    html += '<div class="flex-grow-1 ' + (sender === 'bot' ? 'ms-3' : 'me-3') + '">';
    html += '<div class="' + bgClass + ' p-3 rounded">';
    html += (sender === 'bot' ? '<strong>HouraBot AI:</strong> ' : '<strong>You:</strong> ');
    html += escapeHtml(text);
    html += '</div></div>';
    if (sender === 'user') {
        html += '<div class="flex-shrink-0"><i class="bi ' + icon + ' text-primary"></i></div>';
    }
    html += '</div>';
    
    messageDiv.innerHTML = html;
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function showTypingIndicator(show) {
    var indicator = document.getElementById('typing-indicator');
    if (indicator) {
        indicator.style.display = show ? 'block' : 'none';
        if (show) {
            var messagesDiv = document.getElementById('chatbot-messages');
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
    }
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/\n/g, '<br>');
}

// Smooth scroll untuk anchor links (kekal sama dari kod asal)
document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        var targetId = this.getAttribute('href');
        if(targetId === '#') return;
        
        var targetElement = document.querySelector(targetId);
        if(targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 80,
                behavior: 'smooth'
            });
        }
    });
});
</script>

<?php else: ?>
<!-- Jika user logged in, redirect ke dashboard -->
<script>
window.location.href = "dashboard.php";
</script>
<?php endif; ?>

<!-- Floating FAQ Button -->
<a href="javascript:void(0);" 
   id="floating-chatbot" 
   onclick="toggleAIChatbot()"
   title="Tanya HouraBot AI" 
   style="position:fixed;right:20px;bottom:20px;z-index:9999;background:#8a2be2;color:white;width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 20px rgba(0,0,0,0.15);text-decoration:none;font-size:24px;transition:transform 0.15s ease;">
    <i class="bi bi-robot"></i>
</a>

<?php include 'includes/footer.php'; ?>