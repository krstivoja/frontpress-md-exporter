const esbuild = require('esbuild');
const path = require('path');
const fs = require('fs');

const isWatch = process.argv.includes('--watch');
const isProduction = process.argv.includes('--production');

const config = {
  entryPoints: {
    index: 'src/admin/index.jsx',
    network: 'src/network/index.jsx',
  },
  outdir: 'dist',
  bundle: true,
  minify: isProduction,
  sourcemap: !isProduction,
  jsx: 'automatic',
  jsxImportSource: 'react',
  format: 'iife',
  target: 'es2019',
  loader: {
    '.css': 'css',
    '.svg': 'dataurl',
  },
  define: {
    'process.env.NODE_ENV': isProduction ? '"production"' : '"development"',
  },
};

async function run() {
  fs.mkdirSync(path.join(__dirname, 'dist'), { recursive: true });
  if (isWatch) {
    const ctx = await esbuild.context(config);
    await ctx.watch();
    console.log('watching…');
  } else {
    await esbuild.build(config);
    console.log('built');
  }
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});
