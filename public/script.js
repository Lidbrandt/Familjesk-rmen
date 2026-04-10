let images = [];
let currentIndex = 0;

async function loadImages() {
  try {
    const response = await fetch('/api/images');
    const data = await response.json();
    images = data.images;
    document.getElementById('image-count').textContent = data.count + ' bilder';

    if (images.length > 0) {
      showImage(0);
      setInterval(nextImage, 10000);
    } else {
      document.getElementById('image-count').textContent = 'Inga bilder hittades';
    }
  } catch (err) {
    document.getElementById('image-count').textContent = 'Kunde inte ladda bilder';
  }
}

function showImage(index) {
  var img = document.getElementById('current-image');
  img.style.opacity = 0;
  setTimeout(function () {
    img.src = images[index];
    img.onload = function () { img.style.opacity = 1; };
  }, 500);
}

function nextImage() {
  currentIndex = (currentIndex + 1) % images.length;
  showImage(currentIndex);
}

// Uppdatera bildlistan var 60:e sekund för att fånga nya bilder
setInterval(async function () {
  try {
    var response = await fetch('/api/images');
    var data = await response.json();
    images = data.images;
    document.getElementById('image-count').textContent = data.count + ' bilder';
  } catch (err) {
    // Ignorera uppdateringsfel
  }
}, 60000);

loadImages();
