import { chromium } from '/Users/aaronfoster/node_modules/playwright/index.mjs';
import fs from 'fs';
const FONTS='/Users/aaronfoster/Local Sites/afb-digital-solutions/app/public/wp-content/themes/afb/assets/fonts/';
const b64=p=>fs.readFileSync(p).toString('base64');
const FACE=`@font-face{font-family:Manrope;src:url(data:font/woff2;base64,${b64(FONTS+'manrope.woff2')}) format('woff2');font-weight:200 800}
@font-face{font-family:PJS;src:url(data:font/woff2;base64,${b64(FONTS+'plus-jakarta-sans.woff2')}) format('woff2');font-weight:200 800}`;
const shield=(s,stroke=7)=>`<svg width="${s}" height="${s}" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
<path d="M50 8 L84 20 V48 C84 70 69 85 50 92 C31 85 16 70 16 48 V20 Z" fill="#2a1a15" stroke="#e84e36" stroke-width="${stroke}" stroke-linejoin="round"/>
<rect x="38" y="34" width="8" height="30" rx="3" fill="#fff"/><rect x="54" y="34" width="8" height="30" rx="3" fill="#fff"/></svg>`;
const ICON=s=>`<style>${FACE}html,body{margin:0;width:${s}px;height:${s}px}</style>
<div style="width:${s}px;height:${s}px;background:radial-gradient(circle at 30% 20%,#3a231c,#1c100d 70%);display:flex;align-items:center;justify-content:center">${shield(s*0.78, 6)}</div>`;
const BANNER=(w,h)=>{const k=w/772;return `<style>${FACE}html,body{margin:0}</style>
<div style="width:${w}px;height:${h}px;box-sizing:border-box;background:radial-gradient(circle at 85% 30%,#3a231c,#1c100d 60%);position:relative;overflow:hidden;font-family:Manrope,sans-serif;color:#fff">
<div style="position:absolute;right:${50*k}px;top:${20*k}px;opacity:.95">${shield(210*k,5)}</div>
<div style="position:absolute;left:${48*k}px;top:${62*k}px;width:${470*k}px">
<div style="font:800 ${13*k}px/1 Manrope;letter-spacing:.18em;color:#ffb3a3;text-transform:uppercase;margin-bottom:${12*k}px">Plugin supply chain safety</div>
<div style="font:800 ${46*k}px/1.05 PJS;letter-spacing:-.01em">AFB Update Watch</div>
<div style="width:${44*k}px;height:${4*k}px;background:#e84e36;border-radius:2px;margin:${16*k}px 0"></div>
<div style="font:500 ${18*k}px/1.45 Manrope;color:#d8ceca">Know when a plugin changes owner or is closed, and hold its updates until you have checked it.</div>
</div></div>`};
const b=await chromium.launch({headless:true,executablePath:'/Users/aaronfoster/Library/Caches/ms-playwright/chromium-1223/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing'});
const p=await b.newPage();
for(const [n,w,h,html] of [['banner-772x250',772,250,BANNER(772,250)],['banner-1544x500',1544,500,BANNER(1544,500)],['icon-128x128',128,128,ICON(128)],['icon-256x256',256,256,ICON(256)]]){
  await p.setViewportSize({width:w,height:h}); await p.setContent(html); await p.waitForTimeout(200);
  await p.screenshot({path:n+'.png',clip:{x:0,y:0,width:w,height:h}});
}
await b.close();
