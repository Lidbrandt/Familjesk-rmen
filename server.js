const express = require('express');
const fs = require('fs');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;
const IMAGES_DIR = process.env.IMAGES_DIR || path.join(__dirname, 'images');

const IMAGE_EXTENSIONS = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.tiff', '.tif', '.heic', '.heif'];

function getImageFiles(dir) {
  const files = [];
  try {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    for (const entry of entries) {
      const fullPath = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        files.push(...getImageFiles(fullPath));
      } else if (entry.isFile()) {
        const ext = path.extname(entry.name).toLowerCase();
        if (IMAGE_EXTENSIONS.includes(ext)) {
          files.push(fullPath);
        }
      }
    }
  } catch (err) {
    console.error(`Fel vid läsning av mapp ${dir}:`, err.message);
  }
  return files;
}

app.use(express.static(path.join(__dirname, 'public')));

app.use('/photos', express.static(IMAGES_DIR));

app.get('/api/images', (req, res) => {
  const imageFiles = getImageFiles(IMAGES_DIR);
  const relativePaths = imageFiles.map(f => '/photos/' + path.relative(IMAGES_DIR, f));
  res.json({
    count: imageFiles.length,
    images: relativePaths
  });
});

app.listen(PORT, () => {
  console.log(`Familjeskärmen körs på http://localhost:${PORT}`);
  console.log(`Bildmapp: ${IMAGES_DIR}`);
  const imageFiles = getImageFiles(IMAGES_DIR);
  console.log(`Antal bilder: ${imageFiles.length}`);
});
