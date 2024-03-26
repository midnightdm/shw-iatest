const fs = require('fs');
const path = require('path');

const sourceDir = 'htdocs';
const destinationDir = 'htdocs/motion';

// Ensure the destination directory exists
if (!fs.existsSync(destinationDir)) {
  fs.mkdirSync(destinationDir);
}

// List all files in the source directory
const files = fs.readdirSync(sourceDir);

// Move 'motion.js' and 'motion.js.map' to the destination directory
files.forEach(file => {
  if (file === 'motion.js' || file === 'motion.js.map') {
    const sourcePath = path.join(sourceDir, file);
    const destinationPath = path.join(destinationDir, file);

    // Rename or move the file
    fs.renameSync(sourcePath, destinationPath);
    console.log(`Moved ${file} to ${destinationDir}`);
  }
});

console.log('File move complete.');
