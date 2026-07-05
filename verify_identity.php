<?php
require_once 'includes/session_start.php';
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'includes/header2.php'; 
?>

<div class="container py-5">
    <div class="card shadow border-0">
        <div class="card-body p-5 text-center">
            <h2 class="fw-bold mb-4">Identity Verification</h2>
            
            <div id="loader">
                <div class="spinner-border text-primary mb-3"></div>
                <p>Loading AI Biometric System...</p>
            </div>

            <div id="verify-ui" style="display:none;">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-3 border rounded">
                            <h6>1. Upload Identity Card</h6>
                            <input type="file" id="idUpload" class="form-control mb-2" accept="image/*">
                            <div id="id-preview" style="height:200px;" class="bg-light d-flex align-items-center justify-content-center overflow-hidden rounded">
                                <i class="bi bi-card-image display-1 text-muted"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded">
                            <h6>2. Take Selfie</h6>
                            <video id="video" width="100%" height="200" autoplay muted class="bg-dark rounded"></video>
                        </div>
                    </div>
                </div>

                <button id="btn-verify" class="btn btn-primary btn-lg mt-4 w-100">
                    Verify Now
                </button>
            </div>

            <div id="status-result" class="mt-4 p-3 rounded d-none"></div>
        </div>
    </div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
    window.onload = async () => {
        const video = document.getElementById('video');
        
        await Promise.all([
            faceapi.nets.ssdMobilenetv1.loadFromUri('/hourawebsystem/models'),
            faceapi.nets.faceLandmark68Net.loadFromUri('/hourawebsystem/models'),
            faceapi.nets.faceRecognitionNet.loadFromUri('/hourawebsystem/models')
        ]);

        document.getElementById('loader').style.display = 'none';
        document.getElementById('verify-ui').style.display = 'block';

        navigator.mediaDevices.getUserMedia({ video: {} }).then(stream => video.srcObject = stream);

        document.getElementById('idUpload').addEventListener('change', function(e) {
            const reader = new FileReader();
            reader.onload = (event) => {
                document.getElementById('id-preview').innerHTML = `<img src="${event.target.result}" id="idImg" style="width:100%">`;
            };
            reader.readAsDataURL(e.target.files[0]);
        });

        document.getElementById('btn-verify').onclick = async () => {
            const idImg = document.getElementById('idImg');
            if(!idImg) return alert("Please upload an ID image!");

            const status = document.getElementById('status-result');
            status.className = "mt-4 p-3 rounded alert alert-info";
            status.innerText = "Verifying...";
            status.classList.remove('d-none');

            const idResult = await faceapi.detectSingleFace(idImg).withFaceLandmarks().withFaceDescriptor();
            const selfieResult = await faceapi.detectSingleFace(video).withFaceLandmarks().withFaceDescriptor();

            if (idResult && selfieResult) {
                const dist = faceapi.euclideanDistance(idResult.descriptor, selfieResult.descriptor);
                if (dist < 0.6) {
                    status.className = "mt-4 p-3 rounded alert alert-success";
                    status.innerHTML = "✅ Verification Successful! Your identity has been verified.";
                    
                    fetch('update_verification.php', { method: 'POST' })
                    .then(() => window.location.href = 'profile.php?success=Verified');
                } else {
                    status.className = "mt-4 p-3 rounded alert alert-danger";
                    status.innerText = "❌ Face does not match. Please try again.";
                }
            } else {
                status.className = "mt-4 p-3 rounded alert alert-warning";
                status.innerText = "Face not detected. Please ensure good lighting.";
            }
        };
    };
</script>
<?php include 'includes/footer.php'; ?>