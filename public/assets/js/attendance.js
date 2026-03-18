document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('qrModal');
  const video = document.getElementById('qrVideo');
  const canvas = document.getElementById('qrCanvas');
  const ctx = canvas.getContext('2d');
  const qrResult = document.getElementById('qrResult');
  let scanning = false;

  window.openQRModal = function() {
    modal.style.display = 'flex';
    startQRScan();
  }

  window.closeQRModal = function() {
    modal.style.display = 'none';
    scanning = false;
    if (video.srcObject) {
      video.srcObject.getTracks().forEach(track => track.stop());
    }
    qrResult.textContent = '';
  }

  function startQRScan() {
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(stream => {
        scanning = true;
        video.srcObject = stream;
        video.setAttribute('playsinline', true);
        video.play();
        requestAnimationFrame(scanFrame);
      })
      .catch(err => {
        alert('Camera access denied: ' + err);
        closeQRModal();
      });
  }

  function scanFrame() {
    if (!scanning) return;
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(imageData.data, canvas.width, canvas.height);
      if (code) {
        scanning = false;
        video.srcObject.getTracks().forEach(track => track.stop());
        qrResult.textContent = "QR Code detected: " + code.data;
        // TODO: update your table or send to backend here
        return;
      }
    }
    requestAnimationFrame(scanFrame);
  }

  // Expose startQRScan for button fallback if needed
  window.startQRScan = startQRScan;
});
