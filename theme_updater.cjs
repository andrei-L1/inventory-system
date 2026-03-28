const fs = require('fs');
const path = require('path');

const directory = 'c:/xampp/htdocs/inventory-system/resources/js/Pages';

const colorMap = {
    '#010409': 'var(--bg-deep)',
    '#0d1117': 'var(--bg-deep)',
    '#161b22': 'var(--bg-panel)',
    '#22272e': 'var(--bg-panel-hover)',
    '#2d333b': 'var(--bg-panel-hover)',
    '#30363d': 'var(--bg-panel-border)',
    '#cdd9e5': 'var(--text-primary)',
    '#adbac7': 'var(--text-primary)',
    '#8b949e': 'var(--text-secondary)',
    '#f0f6fc': 'var(--text-primary)',
    '#c9d1d9': 'var(--text-primary)',
    '#58a6ff': 'var(--text-primary)',
    '#1f6feb': 'var(--text-muted)',
    '#238636': 'var(--accent-primary)',
    'rgba\\(35, 134, 54, 0.7\\)': 'rgba(250, 250, 250, 0.7)',
    '#57ab5a': 'var(--accent-primary)',
    'linear-gradient\\(135deg, #1f6feb, #58a6ff\\)': 'var(--bg-panel-hover)'
};

function processDirectory(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            processDirectory(fullPath);
        } else if (fullPath.endsWith('.vue')) {
            let content = fs.readFileSync(fullPath, 'utf8');
            let modified = false;
            
            const gradientRegex = new RegExp('linear-gradient\\(135deg, #1f6feb, #58a6ff\\)', 'gi');
            if (gradientRegex.test(content)) {
                content = content.replace(gradientRegex, 'var(--bg-panel-hover)');
                modified = true;
            }

            for (const [hex, variable] of Object.entries(colorMap)) {
                if (hex.includes('gradient')) continue;
                
                const regex = new RegExp(hex, 'gi');
                if (regex.test(content)) {
                    content = content.replace(regex, variable);
                    modified = true;
                }
            }
            if (modified) {
                fs.writeFileSync(fullPath, content);
                console.log(`Updated ${fullPath}`);
            }
        }
    }
}

processDirectory(directory);
console.log("Color replacement complete.");
