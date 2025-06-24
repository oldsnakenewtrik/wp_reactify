#!/usr/bin/env node

/**
 * ReactifyWP Build Script
 * Automates the process of building and packaging React apps for ReactifyWP
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const archiver = require('archiver');

// Configuration
const config = {
  sourceDir: './complex-app-conversion',
  buildDir: './complex-app-conversion/dist',
  outputDir: './reactifywp-packages',
  appName: 'sondercare-bed-selector',
  version: '1.0.0'
};

// Colors for console output
const colors = {
  reset: '\x1b[0m',
  bright: '\x1b[1m',
  red: '\x1b[31m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  magenta: '\x1b[35m',
  cyan: '\x1b[36m'
};

function log(message, color = 'reset') {
  console.log(`${colors[color]}${message}${colors.reset}`);
}

function logStep(step, message) {
  log(`\n🔧 Step ${step}: ${message}`, 'cyan');
}

function logSuccess(message) {
  log(`✅ ${message}`, 'green');
}

function logError(message) {
  log(`❌ ${message}`, 'red');
}

function logWarning(message) {
  log(`⚠️  ${message}`, 'yellow');
}

// Ensure directory exists
function ensureDir(dir) {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
    log(`Created directory: ${dir}`, 'blue');
  }
}

// Copy files
function copyFiles() {
  logStep(1, 'Copying source files to conversion directory');
  
  ensureDir(config.sourceDir);
  ensureDir(path.join(config.sourceDir, 'src'));
  
  // Copy main app files from project directory
  const projectDir = './project';
  
  if (fs.existsSync(projectDir)) {
    // Copy package.json and update it
    const packageJson = JSON.parse(fs.readFileSync(path.join(projectDir, 'package.json'), 'utf8'));
    packageJson.name = config.appName;
    packageJson.version = config.version;
    
    // Add archiver dependency for build script
    if (!packageJson.devDependencies) packageJson.devDependencies = {};
    packageJson.devDependencies.archiver = '^5.3.1';
    
    fs.writeFileSync(
      path.join(config.sourceDir, 'package.json'),
      JSON.stringify(packageJson, null, 2)
    );
    
    // Copy TypeScript config
    if (fs.existsSync(path.join(projectDir, 'tsconfig.json'))) {
      fs.copyFileSync(
        path.join(projectDir, 'tsconfig.json'),
        path.join(config.sourceDir, 'tsconfig.json')
      );
    }
    
    // Copy source files
    const srcDir = path.join(projectDir, 'src');
    if (fs.existsSync(srcDir)) {
      copyDirectory(srcDir, path.join(config.sourceDir, 'src'));
    }
    
    // Copy public files if they exist
    const publicDir = path.join(projectDir, 'public');
    if (fs.existsSync(publicDir)) {
      copyDirectory(publicDir, path.join(config.sourceDir, 'public'));
    }
    
    logSuccess('Source files copied');
  } else {
    logWarning('Project directory not found, using existing conversion files');
  }
}

function copyDirectory(src, dest) {
  ensureDir(dest);
  const files = fs.readdirSync(src);
  
  files.forEach(file => {
    const srcPath = path.join(src, file);
    const destPath = path.join(dest, file);
    
    if (fs.statSync(srcPath).isDirectory()) {
      copyDirectory(srcPath, destPath);
    } else {
      fs.copyFileSync(srcPath, destPath);
    }
  });
}

// Install dependencies
function installDependencies() {
  logStep(2, 'Installing dependencies');
  
  try {
    process.chdir(config.sourceDir);
    execSync('npm install', { stdio: 'inherit' });
    logSuccess('Dependencies installed');
  } catch (error) {
    logError('Failed to install dependencies');
    throw error;
  } finally {
    process.chdir('..');
  }
}

// Build the app
function buildApp() {
  logStep(3, 'Building React app for production');
  
  try {
    process.chdir(config.sourceDir);
    execSync('npm run build', { stdio: 'inherit' });
    logSuccess('App built successfully');
  } catch (error) {
    logError('Build failed');
    throw error;
  } finally {
    process.chdir('..');
  }
}

// Validate build output
function validateBuild() {
  logStep(4, 'Validating build output');
  
  const indexPath = path.join(config.buildDir, 'index.html');
  const assetsDir = path.join(config.buildDir, 'assets');
  
  if (!fs.existsSync(indexPath)) {
    throw new Error('index.html not found in build output');
  }
  
  if (!fs.existsSync(assetsDir)) {
    throw new Error('assets directory not found in build output');
  }
  
  const assets = fs.readdirSync(assetsDir);
  const hasJS = assets.some(file => file.endsWith('.js'));
  const hasCSS = assets.some(file => file.endsWith('.css'));
  
  if (!hasJS) {
    throw new Error('No JavaScript files found in assets');
  }
  
  log(`Found ${assets.length} asset files:`, 'blue');
  assets.forEach(file => log(`  - ${file}`, 'blue'));
  
  logSuccess('Build output validated');
}

// Create ZIP package
function createPackage() {
  logStep(5, 'Creating ReactifyWP package');
  
  ensureDir(config.outputDir);
  
  const zipPath = path.join(config.outputDir, `${config.appName}-v${config.version}.zip`);
  
  return new Promise((resolve, reject) => {
    const output = fs.createWriteStream(zipPath);
    const archive = archiver('zip', { zlib: { level: 9 } });
    
    output.on('close', () => {
      const sizeKB = Math.round(archive.pointer() / 1024);
      logSuccess(`Package created: ${zipPath} (${sizeKB} KB)`);
      resolve(zipPath);
    });
    
    archive.on('error', reject);
    archive.pipe(output);
    
    // Add all files from dist directory
    archive.directory(config.buildDir, false);
    archive.finalize();
  });
}

// Generate deployment instructions
function generateInstructions(zipPath) {
  logStep(6, 'Generating deployment instructions');
  
  const instructions = `
# SonderCare Bed Selector - ReactifyWP Deployment

## 📦 Package Information
- **App Name**: ${config.appName}
- **Version**: ${config.version}
- **Package**: ${path.basename(zipPath)}
- **Build Date**: ${new Date().toISOString()}

## 🚀 Deployment Steps

### 1. Upload to WordPress
1. Go to **WordPress Admin → Settings → ReactifyWP**
2. Click "Upload Project"
3. Select the ZIP file: \`${path.basename(zipPath)}\`
4. Set the following:
   - **Project Slug**: \`${config.appName}\`
   - **Shortcode Name**: \`${config.appName}\` (or customize)

### 2. Use in Content
Add this shortcode to any post, page, or widget:

\`\`\`
[reactify slug="${config.appName}"]
\`\`\`

### 3. Advanced Options
For custom styling or sizing:

\`\`\`
[reactify slug="${config.appName}" height="600px" class="custom-wrapper"]
\`\`\`

## 🔧 Troubleshooting

### If the app doesn't load:
1. Check browser console for errors
2. Verify the shortcode slug matches the uploaded project
3. Ensure ReactifyWP plugin is active and up to date

### For styling issues:
- The app includes responsive design
- Custom CSS can be added to your theme
- Use browser dev tools to inspect and adjust

## 📋 Features Included
- Multi-step bed selection quiz
- Dynamic pricing calculations
- Responsive design
- Error handling and validation
- Progress tracking
- Results summary with recommendations

## 🆘 Support
If you encounter issues:
1. Check the browser console for error messages
2. Verify WordPress and plugin versions
3. Test in a different browser
4. Contact support with error details

---
Generated on ${new Date().toLocaleString()}
`;

  const instructionsPath = path.join(config.outputDir, `${config.appName}-deployment-instructions.md`);
  fs.writeFileSync(instructionsPath, instructions.trim());
  
  logSuccess(`Instructions saved: ${instructionsPath}`);
}

// Main execution
async function main() {
  try {
    log('\n🚀 ReactifyWP Build Process Starting', 'bright');
    log('=====================================', 'bright');
    
    copyFiles();
    installDependencies();
    buildApp();
    validateBuild();
    
    const zipPath = await createPackage();
    generateInstructions(zipPath);
    
    log('\n🎉 Build Process Complete!', 'green');
    log('========================', 'green');
    log(`\n📦 Package ready: ${zipPath}`, 'cyan');
    log(`📋 Instructions: ${config.outputDir}/${config.appName}-deployment-instructions.md`, 'cyan');
    log('\n✨ Your complex React app is now ready for ReactifyWP!', 'bright');
    
  } catch (error) {
    logError(`\nBuild failed: ${error.message}`);
    process.exit(1);
  }
}

// Run if called directly
if (require.main === module) {
  main();
}

module.exports = { main, config };
