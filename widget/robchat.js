"use strict";var HWChat=(()=>{var l=Object.defineProperty;var y=Object.getOwnPropertyDescriptor;var x=Object.getOwnPropertyNames;var v=Object.prototype.hasOwnProperty;var w=(n,e)=>{for(var t in e)l(n,t,{get:e[t],enumerable:!0})},E=(n,e,t,i)=>{if(e&&typeof e=="object"||typeof e=="function")for(let a of x(e))!v.call(n,a)&&a!==t&&l(n,a,{get:()=>e[a],enumerable:!(i=y(e,a))||i.enumerable});return n};var k=n=>E(l({},"__esModule",{value:!0}),n);var B={};w(B,{HWChatWidget:()=>r});function c(n){let e=n.accentColor||"#3B7DD8",t=n.accentGradient||`linear-gradient(135deg, #3B7DD8, #1B2A4A)`,i=n.aiAccent||"#1B2A4A",o=(n.position||"bottom-right")==="bottom-right",hbg=n.colorHeaderBg||t,htxt=n.colorHeaderText||"#ffffff",sec=n.colorSecondary||e,qbg=n.colorQuickBtnBg||"transparent",qtxt=n.colorQuickBtnText||e,ubub=n.colorUserBubble||t,aiborder=n.colorAiBubbleBorder||e,fbg=n.colorFooterBg||'transparent',ftxt=n.colorFooterText||'#6B7A94',sbtn=n.colorSendBtn||e;return`
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=DM+Sans:wght@300;400;500&display=swap');

:host {
  all: initial;
  font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
  color: #1B2A4A;
  line-height: 1.4;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

.rc-bubble {
  position: fixed;
  bottom: 24px;
  ${o?"right: 100px":"left: 24px"};
  width: 56px;
  height: 56px;
  background: ${t};
  border: none;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 99999;
  box-shadow: 0 4px 24px rgba(27,42,74,0.35);
  transition: transform 0.15s ease;
}
.rc-bubble:hover { transform: scale(1.05); }
.rc-bubble:active { transform: scale(0.95); }
.rc-bubble svg { transition: transform 0.15s ease, opacity 0.15s ease; }

.rc-unread {
  position: absolute;
  top: -4px;
  ${o?"right: -4px":"left: -4px"};
  width: 20px;
  height: 20px;
  background: ${e};
  border: 2px solid #F7F9FC;
  border-radius: 50%;
  color: #fff;
  font-size: 11px;
  font-weight: 500;
  font-family: 'DM Sans', sans-serif;
  display: flex;
  align-items: center;
  justify-content: center;
}

.rc-window {
  position: fixed;
  bottom: 92px;
  ${o?"right: 100px":"left: 24px"};
  width: 400px;
  max-width: calc(100vw - 32px);
  height: 600px;
  max-height: calc(100vh - 120px);
  background: #F7F9FC;
  border: 1px solid rgba(27,42,74,0.25);
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  z-index: 99998;
  overflow: hidden;
  box-shadow: 0 8px 48px rgba(15,29,54,0.15);
  opacity: 0;
  transform: translateY(20px) scale(0.95);
  transition: opacity 0.25s ease, transform 0.25s ease;
  pointer-events: none;
}
.rc-window.rc-open {
  opacity: 1;
  transform: translateY(0) scale(1);
  pointer-events: auto;
}

/* --- Header --- */
.rc-header {
  padding: 16px 20px;
  border-bottom: none;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: ${hbg};
  flex-shrink: 0;
}
.rc-header-info {
  display: flex;
  align-items: center;
  gap: 12px;
}
.rc-status-dot {
  width: 8px;
  height: 8px;
  background: #4ADE80;
  border-radius: 50%;
  flex-shrink: 0;
}
.rc-header-name {
  font-size: 16px;
  font-weight: 600;
  color: ${htxt};
  font-family: 'Playfair Display', serif;
}
.rc-header-sub {
  font-size: 11px;
  color: ${htxt}cc;
  font-family: 'DM Sans', sans-serif;
  font-weight: 400;
  margin-top: 2px;
}
.rc-header-actions {
  display: flex;
  gap: 4px;
}
.rc-header-btn {
  background: none;
  border: none;
  color: ${htxt}cc;
  cursor: pointer;
  padding: 6px 8px;
  font-size: 16px;
  font-family: inherit;
  display: flex;
  align-items: center;
}
.rc-header-btn:hover { color: ${htxt}; }

/* --- Messages --- */
.rc-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.rc-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px 16px 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.rc-messages::-webkit-scrollbar { width: 4px; }
.rc-messages::-webkit-scrollbar-track { background: transparent; }
.rc-messages::-webkit-scrollbar-thumb { background: rgba(27,42,74,0.3); border-radius: 4px; }

.rc-msg {
  display: flex;
}
.rc-msg-user { justify-content: flex-end; }
.rc-msg-assistant { justify-content: flex-start; }

.rc-msg-bubble {
  max-width: 82%;
  padding: 10px 14px;
  border-radius: 14px;
}
.rc-msg-user .rc-msg-bubble {
  background: ${ubub};
  border: none;
  border-bottom-right-radius: 4px;
  box-shadow: 0 2px 8px rgba(27,42,74,0.2);
}
.rc-msg-assistant .rc-msg-bubble {
  background: #fff;
  border: 1px solid rgba(27,42,74,0.12);
  border-left: 3px solid ${aiborder};
  border-bottom-left-radius: 4px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.rc-msg-text {
  font-size: 13.5px;
  line-height: 1.55;
  font-family: 'DM Sans', sans-serif;
  font-weight: 300;
  word-break: break-word;
}
.rc-msg-user .rc-msg-text { color: #fff; }
.rc-msg-assistant .rc-msg-text { color: #1B2A4A; }
.rc-msg-text a {
  color: ${i};
  text-decoration: underline;
}
.rc-msg-text a:hover { opacity: 0.8; }
.rc-msg-text strong { font-weight: 600; }
.rc-msg-text em { font-style: italic; }
.rc-msg-text code {
  background: rgba(27,42,74,0.1);
  padding: 1px 5px;
  border-radius: 3px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
}
.rc-msg-text pre {
  background: rgba(27,42,74,0.06);
  padding: 10px 12px;
  overflow-x: auto;
  margin: 6px 0;
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  line-height: 1.5;
  border: 1px solid rgba(27,42,74,0.15);
  border-radius: 6px;
}

.rc-msg-time {
  font-size: 10px;
  color: #6B7A94;
  font-family: 'DM Sans', sans-serif;
  font-weight: 400;
  margin-top: 4px;
  text-align: right;
}
.rc-msg-user .rc-msg-time { color: rgba(255,255,255,0.65); }

/* --- Typing indicator --- */
.rc-typing {
  display: flex;
  gap: 4px;
  padding: 4px 2px;
}
.rc-typing-dot {
  width: 6px;
  height: 6px;
  background: ${e};
  border-radius: 50%;
  animation: rc-pulse 1.2s infinite ease-in-out;
}
.rc-typing-dot:nth-child(2) { animation-delay: 0.15s; }
.rc-typing-dot:nth-child(3) { animation-delay: 0.3s; }

@keyframes rc-pulse {
  0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
  40% { opacity: 1; transform: scale(1); }
}

/* --- Quick replies --- */
.rc-quick-replies {
  padding: 8px 16px 12px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  background: ${e}06;
}
.rc-quick-btn {
  background: ${qbg};
  border: 1px solid ${qtxt};
  color: ${qtxt};
  font-size: 12px;
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  font-weight: 500;
  white-space: nowrap;
  transition: background 0.15s ease;
}
.rc-quick-btn:hover {
  background: ${qtxt}18;
}

/* --- Input bar --- */
.rc-input-bar {
  padding: 12px 16px;
  border-top: 1px solid rgba(27,42,74,0.15);
  display: flex;
  align-items: flex-end;
  gap: 8px;
  background: ${e}08;
  flex-shrink: 0;
}
.rc-toolbar {
  display: flex;
  gap: 4px;
  padding-bottom: 4px;
}
.rc-tool-btn {
  background: none;
  border: none;
  color: #6B7A94;
  cursor: pointer;
  padding: 4px;
  display: flex;
  transition: color 0.15s ease;
}
.rc-tool-btn:hover { color: ${e}; }

.rc-textarea {
  flex: 1;
  background: #fff;
  border: 1px solid rgba(27,42,74,0.25);
  border-radius: 8px;
  color: #1B2A4A;
  font-size: 13.5px;
  font-family: 'DM Sans', sans-serif;
  font-weight: 300;
  padding: 10px 12px;
  resize: none;
  outline: none;
  max-height: 100px;
  line-height: 1.4;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.rc-textarea::placeholder { color: #6B7A94; }
.rc-textarea:focus { border-color: ${e}; box-shadow: 0 0 0 3px rgba(27,42,74,0.1); }

.rc-send-btn {
  background: ${sbtn};
  border: none;
  border-radius: 8px;
  color: #fff;
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: opacity 0.15s ease;
}
.rc-send-btn:disabled { opacity: 0.4; cursor: default; }
.rc-send-btn:not(:disabled):hover { opacity: 0.9; }

/* --- Footer --- */
.rc-footer {
  padding: 10px 16px;
  border-top: none;
  text-align: center;
  flex-shrink: 0;
  background: ${fbg};
}
.rc-footer a {
  font-size: 10px;
  color: ${ftxt};
  font-family: 'DM Sans', sans-serif;
  font-weight: 400;
  letter-spacing: 0.04em;
  text-decoration: none;
  transition: color 0.15s ease;
}
.rc-footer a:hover { color: ${e}; }
.rc-footer a span {
  color: ${e};
  font-weight: 500;
}

/* --- Lead form --- */
.rc-form {
  flex: 1;
  overflow-y: auto;
  padding: 24px 20px;
}
.rc-form-title {
  font-size: 20px;
  font-weight: 600;
  color: #1B2A4A;
  font-family: 'Playfair Display', serif;
  margin: 0 0 4px;
}
.rc-form-desc {
  font-size: 13px;
  color: #6B7A94;
  font-family: 'DM Sans', sans-serif;
  font-weight: 300;
  margin: 0 0 20px;
}
.rc-field { margin-bottom: 16px; }
.rc-label {
  display: block;
  font-size: 11px;
  font-weight: 500;
  color: #6B7A94;
  font-family: 'DM Sans', sans-serif;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 6px;
}
.rc-input {
  width: 100%;
  background: #fff;
  border: 1px solid rgba(27,42,74,0.25);
  border-radius: 6px;
  color: #1B2A4A;
  font-size: 14px;
  font-family: 'DM Sans', sans-serif;
  font-weight: 300;
  padding: 10px 12px;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.rc-input::placeholder { color: rgba(140,123,94,0.5); }
.rc-input:focus { border-color: ${e}; box-shadow: 0 0 0 3px rgba(27,42,74,0.1); }\n.rc-textarea { resize: vertical; min-height: 60px; font-family: inherit; }\n.rc-builder-list { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; }\n.rc-builder-btn { background: #fff; border: 1px solid rgba(27,42,74,0.2); border-radius: 8px; padding: 14px 16px; font-size: 14px; font-family: \'DM Sans\', sans-serif; color: #1B2A4A; cursor: pointer; text-align: left; transition: border-color 0.15s ease, background 0.15s ease; }\n.rc-builder-btn:hover { border-color: ${e}; background: rgba(27,42,74,0.03); }
.rc-form-btn {
  width: 100%;
  background: ${t};
  border: none;
  border-radius: 8px;
  color: #fff;
  font-size: 14px;
  font-weight: 500;
  font-family: 'DM Sans', sans-serif;
  padding: 12px;
  cursor: pointer;
  transition: opacity 0.15s ease;
}
.rc-form-btn:hover { opacity: 0.9; }
.rc-form-err {
  color: #C45D4F;
  font-size: 12px;
  margin-bottom: 12px;
}
.rc-form-success {
  text-align: center;
  padding: 32px 16px;
}
.rc-check-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  background: rgba(27,42,74,0.1);
  border: 1px solid rgba(27,42,74,0.3);
  border-radius: 50%;
  color: ${e};
  font-size: 24px;
  margin-bottom: 16px;
}

/* --- Mobile --- */
@media (max-width: 480px) {
  .rc-window {
    position: fixed;
    width: calc(100vw - 16px);
    top: calc(env(safe-area-inset-top, 0px) + 12px);
    bottom: 88px;
    height: auto;
    max-height: none;
    border-radius: 16px;
    ${o?"right: 8px":"left: 8px"};
  }
  .rc-bubble {
    bottom: 16px;
    ${o?"right: 80px":"left: 16px"};
  }
}

/* --- Property Cards --- */
.rc-cards {
  display: flex;
  gap: 10px;
  overflow-x: auto;
  padding: 4px 16px 12px;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  margin-top: -4px;
}
.rc-cards::-webkit-scrollbar { display: none; }
.rc-card {
  flex: 0 0 188px;
  background: #fff;
  border: 1px solid rgba(27,42,74,0.12);
  border-radius: 12px;
  overflow: hidden;
  font-family: 'DM Sans', sans-serif;
  box-shadow: 0 2px 8px rgba(27,42,74,0.07);
}
.rc-card-img { width: 100%; height: 108px; object-fit: cover; display: block; }
.rc-card-img-ph {
  width: 100%; height: 108px; background: #e8edf5;
  display: flex; align-items: center; justify-content: center;
}
.rc-card-body { padding: 10px 12px; }
.rc-card-price { font-size: 14px; font-weight: 600; color: ${e}; margin-bottom: 2px; }
.rc-card-addr {
  font-size: 10px; color: #6B7A94; margin-bottom: 5px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.rc-card-specs { font-size: 11px; color: #4A5568; margin-bottom: 6px; }
.rc-card-badge {
  display: inline-block; font-size: 11px; font-weight: 600;
  padding: 3px 8px; border-radius: 8px; margin-top: 4px; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.3px;
}
.rc-card-badge.move-in-ready { background: #d4edda; color: #155724; }
.rc-card-badge.under-construction { background: #fff3cd; color: #856404; }
.rc-card-badge.homesite { background: #e2f0fb; color: #0c5460; }
.rc-card-links { display: flex; gap: 5px; flex-wrap: wrap; }
.rc-card-link {
  font-size: 10px; color: ${e}; text-decoration: none;
  border: 1px solid ${e}; padding: 3px 7px; border-radius: 4px; cursor: pointer;
  background: none; font-family: 'DM Sans', sans-serif; font-weight: 500;
  display: inline-flex; align-items: center; gap: 4px;
}
.rc-card-link:hover { background: ${e}18; opacity: 1; }

/* --- Lightbox --- */
.rc-lightbox {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.88);
  z-index: 999999;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 16px;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.2s ease;
}
.rc-lightbox.rc-lb-open { opacity: 1; pointer-events: auto; }
.rc-lightbox img {
  max-width: 92%; max-height: 85%;
  border-radius: 8px; object-fit: contain;
  box-shadow: 0 8px 40px rgba(0,0,0,0.6);
}
.rc-lb-close {
  position: absolute; top: 12px; right: 12px;
  background: rgba(255,255,255,0.15); border: none; color: #fff;
  font-size: 22px; width: 36px; height: 36px; border-radius: 50%;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: background 0.15s ease;
}
.rc-lb-close:hover { background: rgba(255,255,255,0.28); }
.rc-lb-label {
  position: absolute; bottom: 16px; left: 0; right: 0;
  text-align: center; color: rgba(255,255,255,0.7);
  font-size: 12px; font-family: 'DM Sans', sans-serif;
}
`}function d(n,ac,lc){ac=ac||"#3B7DD8";lc=lc||ac;let e=n.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");return e=e.replace(/```(\w*)\n?([\s\S]*?)```/g,(t,i,a)=>`<pre>${a.trim()}</pre>`),e=e.replace(/`([^`\n]+)`/g,"<code>$1</code>"),e=e.replace(/\*{3}(.+?)\*{3}/g,"<strong><em>$1</em></strong>"),e=e.replace(/_{3}(.+?)_{3}/g,"<strong><em>$1</em></strong>"),e=e.replace(/\*{2}(.+?)\*{2}/g,"<strong>$1</strong>"),e=e.replace(/_{2}(.+?)_{2}/g,"<strong>$1</strong>"),e=e.replace(/(?<!\w)\*([^*\n]+)\*(?!\w)/g,"<em>$1</em>"),e=e.replace(/(?<!\w)_([^_\n]+)_(?!\w)/g,"<em>$1</em>"),e=e.replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)/g,'<img src="$2" alt="$1" style="width:100%;border-radius:8px;margin:6px 0;display:block;max-height:200px;object-fit:cover;" loading="lazy">'),e=e.replace(/\[([^\]]+)\]\(action:(\w+)\)/g,(m,txt,act)=>`<button style="font-size:12px;font-family:'DM Sans',sans-serif;font-weight:600;padding:8px 16px;border-radius:6px;border:none;background:${ac};color:#fff;cursor:pointer;margin:4px 4px 4px 0;display:inline-block;" data-action="${act}">${txt}</button>`),e=e.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g,(m,txt,url)=>{let isImg=/\.(jpg|jpeg|png|gif|webp)/i.test(url)||url.includes("thexo-cdn");return isImg?`<button style="font-size:11px;font-family:'DM Sans',sans-serif;font-weight:500;color:${ac};border:1px solid ${ac};padding:4px 10px;border-radius:4px;background:${ac};color:#fff;cursor:pointer;" data-fpurl="${url}" data-label="${txt}">${txt}</button>`:`<a href="${url}" target="_blank" rel="noopener" style="color:${lc}">${txt}</a>`}),e=e.replace(/(?<!["\w/])(https?:\/\/[^\s<]+)/g,'<a href="$1" target="_blank" rel="noopener">$1</a>'),e=e.replace(/^### (.+)$/gm,`<div style="font-weight:600;font-size:14px;margin:8px 0 4px;font-family:'Playfair Display',serif">$1</div>`),e=e.replace(/^## (.+)$/gm,`<div style="font-weight:600;font-size:15px;margin:8px 0 4px;font-family:'Playfair Display',serif">$1</div>`),e=e.replace(/^# (.+)$/gm,`<div style="font-weight:600;font-size:16px;margin:8px 0 4px;font-family:'Playfair Display',serif">$1</div>`),e=e.replace(/^[\-\*] (.+)$/gm,'<div style="padding-left:16px;position:relative;margin:0;line-height:1.25"><span style="position:absolute;left:4px;color:#6B7A94">•</span>$1</div>'),e=e.replace(/^(\d+)\. (.+)$/gm,`<div style="padding-left:20px;position:relative;margin:0;line-height:1.25"><span style="position:absolute;left:0;color:#6B7A94;font-size:12px;font-family:'DM Sans',sans-serif">$1.</span>$2</div>`),e=e.replace(/\n{2,}/g,'<div style="margin:16px 0"></div>'),e=e.replace(/\n/g,"<br/>"),e=e.replace(/<\/div><br\/>/g,"</div>"),e=e.replace(/<strong>Status:<\/strong>\s*Move In Ready|Status:\s*Move In Ready/g,'<span style="display:inline-block;background:#27ae60;color:#fff;font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;letter-spacing:0.3px;">MOVE IN READY</span>'),e=e.replace(/<strong>Status:<\/strong>\s*Under Construction|Status:\s*Under Construction/g,'<span style="display:inline-block;background:#f39c12;color:#fff;font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;letter-spacing:0.3px;">UNDER CONSTRUCTION</span>'),e=e.replace(/<strong>Status:<\/strong>\s*Home Site|Status:\s*Home Site/g,'<span style="display:inline-block;background:#9b59b6;color:#fff;font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;letter-spacing:0.3px;">HOME SITE</span>'),e}function p(n){return n.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\n/g,"<br/>")}var h=()=>typeof crypto<"u"&&crypto.randomUUID?crypto.randomUUID():`${Date.now()}-${Math.random().toString(36).slice(2)}`,C=n=>n.toLocaleTimeString("en-US",{hour:"numeric",minute:"2-digit"}),m='<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',L='<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',T='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',S='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',M='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="0"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',$="←",r=class{constructor(e){this.messages=[];this.isOpen=!1;this.isLoading=!1;this.greeted=!1;this.unreadCount=0;this.currentView="chat";this.leadForm={name:"",email:"",phone:"",message:""};this.leadDone=!1;this.leadErr="";this.availableDates=[];this.availableSlots=[];this.bookingForm={date:"",time:"",name:"",email:"",phone:"",notes:"",builder_id:null,builder_name:""};this.calLoading=!1;this.bookingConfirmation=null;this.config=e,this.sessionId=h();let t=document.createElement("div");t.id="robchat-widget",document.body.appendChild(t),this.root=t.attachShadow({mode:"closed"});let i=document.createElement("style");i.textContent=c(e),this.root.appendChild(i),this.buildBubble(),this.buildWindow()}buildBubble(){this.bubbleEl=document.createElement("button"),this.bubbleEl.className="rc-bubble",this.bubbleEl.setAttribute("aria-label","Open chat"),this.bubbleIcon=document.createElement("span"),this.bubbleIcon.innerHTML=m,this.bubbleEl.appendChild(this.bubbleIcon),this.unreadBadge=document.createElement("span"),this.unreadBadge.className="rc-unread",this.unreadBadge.style.display="none",this.bubbleEl.appendChild(this.unreadBadge),this.bubbleEl.addEventListener("click",()=>this.toggle()),this.root.appendChild(this.bubbleEl)}buildWindow(){this.windowEl=document.createElement("div"),this.windowEl.className="rc-window";let e=this.createHeader();this.windowEl.appendChild(e),this.bodyEl=document.createElement("div"),this.bodyEl.className="rc-body",this.windowEl.appendChild(this.bodyEl),this.windowEl.addEventListener("click",(ev)=>{let a=ev.target.closest("button[data-fpurl]");if(a){ev.preventDefault();ev.stopPropagation();this.showLightbox(a.dataset.fpurl,a.dataset.label||"Floor Plan")}let b=ev.target.closest("button[data-action]");if(b){ev.preventDefault();ev.stopPropagation();let act=b.dataset.action;if(act==="calendar"){this.config.builders&&this.config.builders.length>0?this.setView("select-builder"):this.setView("calendar")}}});let t=document.createElement("div");t.className="rc-footer";t.innerHTML=`<span style="color:${this.config.colorFooterText||'#ffffff'};font-size:10px;font-family:'DM Sans',sans-serif;opacity:0.9;">Powered by HWChat</span>`,this.windowEl.appendChild(t),this.buildChatView(),this.root.appendChild(this.windowEl)}createHeader(){let e=document.createElement("div");e.className="rc-header";let t=document.createElement("div");t.className="rc-header-info",t.innerHTML=`
      <div class="rc-status-dot"></div>
      <div>
        <div class="rc-header-name">${this.escAttr(this.config.name)}</div>
        <div class="rc-header-sub">Typically replies instantly</div>
      </div>`,e.appendChild(t);let i=document.createElement("div");i.className="rc-header-actions",this.headerBackBtn=document.createElement("button"),this.headerBackBtn.className="rc-header-btn",this.headerBackBtn.textContent=$,this.headerBackBtn.style.display="none",this.headerBackBtn.addEventListener("click",()=>this.setView("chat")),i.appendChild(this.headerBackBtn);let a=document.createElement("button");return a.className="rc-header-btn",a.textContent="×",a.addEventListener("click",()=>this.toggle()),i.appendChild(a),e.appendChild(i),e}buildChatView(){this.bodyEl.innerHTML="",this.messagesEl=document.createElement("div"),this.messagesEl.className="rc-messages",this.bodyEl.appendChild(this.messagesEl),this.quickRepliesEl=document.createElement("div"),this.quickRepliesEl.className="rc-quick-replies",this.bodyEl.appendChild(this.quickRepliesEl);let e=document.createElement("div");e.className="rc-input-bar";let t=document.createElement("div");t.className="rc-toolbar";let i=document.createElement("button");if(i.className="rc-tool-btn",i.innerHTML=S,i.title="Share your info",i.addEventListener("click",()=>this.setView("lead-form")),t.appendChild(i),this.config.calendarEnabled&&this.config.availabilityEndpoint){let o=document.createElement("button");o.className="rc-tool-btn",o.innerHTML=M,o.title="Book a tour",o.addEventListener("click",()=>{this.config.builders&&this.config.builders.length>0?this.setView("select-builder"):this.setView("calendar")}),t.appendChild(o)}e.appendChild(t),this.inputEl=document.createElement("textarea"),this.inputEl.className="rc-textarea",this.inputEl.placeholder="Ask me anything...",this.inputEl.rows=1,this.inputEl.addEventListener("keydown",o=>{o.key==="Enter"&&!o.shiftKey&&(o.preventDefault(),this.send())}),e.appendChild(this.inputEl);let a=document.createElement("button");a.className="rc-send-btn",a.innerHTML=T,a.addEventListener("click",()=>this.send()),e.appendChild(a),this.bodyEl.appendChild(e),this.renderMessages(),this.renderQuickReplies()}buildLeadFormView(){this.bodyEl.innerHTML="";let e=document.createElement("div");if(e.className="rc-form",this.leadDone){e.innerHTML=`
        <div class="rc-form-success">
          <span class="rc-check-icon">✓</span>
          <p style="color:#6B7A94;font-family:'DM Sans',sans-serif">Info sent! Heading back to chat...</p>
        </div>`,this.bodyEl.appendChild(e),setTimeout(()=>this.setView("chat"),2e3);return}e.innerHTML=`
      <h3 class="rc-form-title">Share Your Info</h3>
      <p class="rc-form-desc">We'll follow up within 24 hours.</p>
      <div class="rc-field"><label class="rc-label">Name *</label><input class="rc-input" data-field="name" placeholder="Your name" /></div>
      <div class="rc-field"><label class="rc-label">Email *</label><input class="rc-input" data-field="email" type="email" placeholder="you@example.com" /></div>\n      <div class="rc-field"><label class="rc-label">Phone</label><input class="rc-input" data-field="phone" type="tel" placeholder="(555) 123-4567" /></div>\n      <div class="rc-field"><label class="rc-label">Message</label><textarea class="rc-input rc-textarea" data-field="message" placeholder="How can we help?" rows="3"></textarea></div>
      <div class="rc-form-err" style="display:none"></div>
      <button class="rc-form-btn">Send</button>`,this.bodyEl.appendChild(e),e.querySelectorAll(".rc-input").forEach(t=>{let i=t.dataset.field;t.value=this.leadForm[i]||"",t.addEventListener("input",()=>{this.leadForm[i]=t.value})}),e.querySelector(".rc-form-btn").addEventListener("click",()=>this.submitLead())}setView(e){switch(this.currentView=e,this.headerBackBtn.style.display=e!=="chat"?"block":"none",e){case"chat":this.buildChatView(),this.scrollToBottom(),setTimeout(()=>this.inputEl?.focus(),50);break;case"lead-form":this.buildLeadFormView();break;case"select-builder":this.buildBuilderSelectView();break;case"calendar":this.buildCalendarView(),this.loadAvailableDates();break;case"booking-confirm":this.buildBookingConfirmView();break}}toggle(){this.isOpen=!this.isOpen,this.isOpen?(this.windowEl.classList.add("rc-open"),this.bubbleEl.setAttribute("aria-label","Close chat"),this.bubbleIcon.innerHTML=L,this.unreadCount=0,this.unreadBadge.style.display="none",this.greeted||(this.greeted=!0,this.addMessage("assistant",this.config.greeting),this.renderQuickReplies()),setTimeout(()=>this.inputEl?.focus(),300)):(this.windowEl.classList.remove("rc-open"),this.bubbleEl.setAttribute("aria-label","Open chat"),this.bubbleIcon.innerHTML=m)}addMessage(e,t,s=null){this.messages.push({id:h(),role:e,content:t,properties:s,timestamp:new Date}),this.currentView==="chat"&&(this.renderMessages(),e==="assistant"?this.scrollToLastAssistant():this.scrollToBottom()),!this.isOpen&&e==="assistant"&&(this.unreadCount++,this.unreadBadge.textContent=String(this.unreadCount),this.unreadBadge.style.display="flex")}renderMessages(){this.messagesEl&&(this.messagesEl.innerHTML=this.messages.map(e=>{let t=`rc-msg rc-msg-${e.role}`,i=e.role==="assistant"?d(e.content,this.config.accentColor||"#2D5A3D",this.config.aiAccent||"#a12b2f"):p(e.content);let j=`
          <div class="${t}">
            <div class="rc-msg-bubble">
              <div class="rc-msg-text">${i}</div>
              <div class="rc-msg-time">${C(e.timestamp)}</div>
            </div>
          </div>`;return j}).join(""),this.isLoading&&(this.messagesEl.innerHTML+=`
        <div class="rc-msg rc-msg-assistant">
          <div class="rc-msg-bubble">
            <div class="rc-typing">
              <span class="rc-typing-dot"></span>
              <span class="rc-typing-dot"></span>
              <span class="rc-typing-dot"></span>
            </div>
          </div>
        </div>`))}renderQuickReplies(){if(!(!this.quickRepliesEl||!this.config.quickReplies?.length)){if(this.messages.length>2){this.quickRepliesEl.innerHTML="";return}this.quickRepliesEl.innerHTML=this.config.quickReplies.map(e=>`<button class="rc-quick-btn" data-msg="${this.escAttr(e)}">${this.escAttr(e)}</button>`).join(""),this.quickRepliesEl.querySelectorAll(".rc-quick-btn").forEach(e=>{e.addEventListener("click",()=>{let t=e.dataset.msg||"";this.quickRepliesEl.innerHTML="",this.send(t)})})}}scrollToBottom(){this.messagesEl&&requestAnimationFrame(()=>{this.messagesEl.scrollTop=this.messagesEl.scrollHeight})}scrollToLastAssistant(){this.messagesEl&&requestAnimationFrame(()=>{let m=this.messagesEl.querySelectorAll(".rc-msg-assistant");m.length&&m[m.length-1].scrollIntoView({behavior:"smooth",block:"start"})})}async send(e){let t=(e||this.inputEl?.value||"").trim();if(!t||this.isLoading)return;this.inputEl&&(this.inputEl.value=""),this.quickRepliesEl.innerHTML="",this.addMessage("user",t),this.isLoading=!0,this.renderMessages(),this.scrollToBottom();let i=this.messages.slice(-20).map(a=>({role:a.role,content:a.content}));try{let a=await fetch(this.config.apiEndpoint,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({tenant_id:this.config.id,session_id:this.sessionId,message:t,history:i.slice(0,-1),page_url:window.location.href})});if(!a.ok)throw new Error(a.status===429?"Too many messages — try again shortly.":"Something went wrong.");let o=await a.json();this.isLoading=!1,this.addMessage("assistant",o.reply,o.properties||null),this.handleActions(o)}catch(a){this.isLoading=!1,this.addMessage("assistant",a.message||"I hit a snag. Please try again or use the contact form.")}}handleActions(e){e.action==="show_lead_form"&&(e.lead_data&&(this.leadForm={...this.leadForm,...e.lead_data}),setTimeout(()=>this.setView("lead-form"),500)),e.action==="show_calendar"&&setTimeout(()=>this.setView("calendar"),500)}async submitLead(){if(this.leadErr="",!this.leadForm.name.trim()||!this.leadForm.email.trim()){this.leadErr="Name and email are required.",this.showLeadError();return}let e=this.config.leadCaptureEndpoint||this.config.apiEndpoint.replace(/chat\.php$/,"capture-lead.php");try{if(!(await fetch(e,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({tenant_id:this.config.id,conversation_id:this.sessionId,...this.leadForm})})).ok)throw new Error;this.leadDone=!0,this.addMessage("assistant",`Got it, ${this.leadForm.name.split(" ")[0]}! We'll follow up within 24 hours at ${this.leadForm.email}. Anything else?`),this.buildLeadFormView()}catch{this.leadErr="Something went wrong. Please try again.",this.showLeadError()}}showLeadError(){let e=this.bodyEl.querySelector(".rc-form-err");e&&(e.textContent=this.leadErr,e.style.display=this.leadErr?"block":"none")}buildBuilderSelectView(){this.bodyEl.innerHTML="";let e=document.createElement("div");e.className="rc-form",e.innerHTML=`
      <h3 class="rc-form-title">Select a Builder</h3>
      <p class="rc-form-desc">Choose which builder you\'d like to tour with.</p>
      <div class="rc-builder-list"></div>`;let t=e.querySelector(".rc-builder-list");(this.config.builders||[]).forEach(i=>{let o=document.createElement("button");o.className="rc-builder-btn",o.textContent=i.name,o.addEventListener("click",()=>{this.bookingForm.builder_id=i.id,this.bookingForm.builder_name=i.name,this.setView("calendar")}),t.appendChild(o)}),this.bodyEl.appendChild(e)}buildCalendarView(){this.bodyEl.innerHTML="";let e=document.createElement("div");e.className="rc-form",e.innerHTML=`
      <h3 class="rc-form-title">Book a Tour</h3>
      <p class="rc-form-desc">Pick a date and time that works for you.</p>
      <div class="rc-cal-loading" style="text-align:center;padding:32px;color:#6B7A94;font-size:13px;">Loading availability...</div>
      <div class="rc-cal-dates" style="display:none;margin-bottom:16px;"></div>
      <div class="rc-cal-slots" style="display:none;margin-bottom:16px;"></div>
      <div class="rc-cal-form" style="display:none;"></div>
    `,this.bodyEl.appendChild(e)}async loadAvailableDates(){if(this.config.availabilityEndpoint){this.calLoading=!0;try{let e=await fetch(`${this.config.availabilityEndpoint}?tenant_id=${encodeURIComponent(this.config.id)}&range=14`);if(!e.ok)throw new Error;let t=await e.json();this.availableDates=(t.dates||[]).filter(i=>i.slot_count>0)}catch{this.availableDates=[]}this.calLoading=!1,this.renderCalendarDates()}}renderCalendarDates(){let e=this.bodyEl.querySelector(".rc-cal-loading"),t=this.bodyEl.querySelector(".rc-cal-dates");if(!(!e||!t)){if(e.style.display="none",this.availableDates.length===0){t.style.display="block",t.innerHTML='<div style="text-align:center;padding:24px;color:#6B7A94;font-size:13px;">No available slots in the next 2 weeks.</div>';return}t.style.display="block",t.innerHTML=`<label class="rc-label">Select a Date</label>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        ${this.availableDates.slice(0,7).map(i=>{let o=new Date(i.date+"T12:00:00").getDate(),s=this.bookingForm.date===i.date,b=s?"rgba(27,42,74,0.15)":"rgba(255,255,255,0.6)",f=s?"1px solid rgba(27,42,74,0.5)":"1px solid rgba(27,42,74,0.2)",u=s?"#1B2A4A":"#1B2A4A";return`<button class="rc-date-btn" data-date="${i.date}" style="display:flex;flex-direction:column;align-items:center;padding:10px 14px;cursor:pointer;font-family:'DM Sans',sans-serif;min-width:56px;background:${b};border:${f};border-radius:8px;color:${u};">
            <span style="font-size:10px;text-transform:uppercase;font-family:'DM Sans',sans-serif;font-weight:500;letter-spacing:0.05em;">${i.day}</span>
            <span style="font-size:18px;font-weight:600;margin-top:2px;font-family:'Playfair Display',serif;">${o}</span>
          </button>`}).join("")}
      </div>`,t.querySelectorAll(".rc-date-btn").forEach(i=>{i.addEventListener("click",()=>{this.bookingForm.date=i.dataset.date||"",this.bookingForm.time="",this.renderCalendarDates(),this.loadSlotsForDate(this.bookingForm.date)})})}}async loadSlotsForDate(e){if(!this.config.availabilityEndpoint)return;let t=this.bodyEl.querySelector(".rc-cal-slots");if(t){t.style.display="block",t.innerHTML='<div style="text-align:center;padding:16px;color:#6B7A94;font-size:12px;">Loading times...</div>';try{let i=await fetch(`${this.config.availabilityEndpoint}?tenant_id=${encodeURIComponent(this.config.id)}&date=${e}`);if(!i.ok)throw new Error;let a=await i.json();this.availableSlots=a.slots||[]}catch{this.availableSlots=[]}this.renderTimeSlots()}}renderTimeSlots(){let e=this.bodyEl.querySelector(".rc-cal-slots");if(e){if(this.availableSlots.length===0){e.innerHTML='<div style="text-align:center;padding:16px;color:#6B7A94;font-size:12px;">No available times for this date.</div>';return}e.innerHTML=`<label class="rc-label">Select a Time</label>
      <div style="display:flex;flex-wrap:wrap;gap:6px;">
        ${this.availableSlots.map(t=>{let i=this.bookingForm.time===t.value,a=i?"rgba(27,42,74,0.15)":"rgba(255,255,255,0.6)",o=i?"1px solid rgba(27,42,74,0.5)":"1px solid rgba(27,42,74,0.2)",s=i?"#1B2A4A":"#1B2A4A";return`<button class="rc-time-btn" data-time="${t.value}" style="padding:8px 14px;font-size:12px;font-family:'DM Sans',sans-serif;font-weight:400;cursor:pointer;background:${a};border:${o};border-radius:6px;color:${s};">${t.time}</button>`}).join("")}
      </div>`,e.querySelectorAll(".rc-time-btn").forEach(t=>{t.addEventListener("click",()=>{this.bookingForm.time=t.dataset.time||"",this.renderTimeSlots(),this.renderBookingForm()})})}}renderBookingForm(){let e=this.bodyEl.querySelector(".rc-cal-form");!e||!this.bookingForm.time||(e.style.display="block",e.innerHTML=`
      <div class="rc-field"><label class="rc-label">Name *</label><input class="rc-input" data-bfield="name" placeholder="Your name" /></div>
      <div class="rc-field"><label class="rc-label">Email *</label><input class="rc-input" data-bfield="email" type="email" placeholder="you@example.com" /></div>
      <div class="rc-field"><label class="rc-label">Phone</label><input class="rc-input" data-bfield="phone" type="tel" placeholder="Optional" /></div>
      <div class="rc-field"><label class="rc-label">What would you like to discuss?</label><textarea class="rc-input" data-bfield="notes" placeholder="Optional" style="min-height:60px;resize:vertical;font-family:'DM Sans',sans-serif;"></textarea></div>
      <div class="rc-form-err" style="display:none"></div>
      <button class="rc-form-btn">Confirm Booking</button>
    `,e.querySelectorAll("[data-bfield]").forEach(t=>{let i=t.dataset.bfield;t.value=this.bookingForm[i]||"",t.addEventListener("input",()=>{this.bookingForm[i]=t.value})}),e.querySelector(".rc-form-btn").addEventListener("click",()=>this.submitBooking()))}async submitBooking(){if(!this.bookingForm.date||!this.bookingForm.time||!this.bookingForm.name.trim()||!this.bookingForm.email.trim()){this.leadErr="Name and email are required.";let t=this.bodyEl.querySelector(".rc-form-err");t&&(t.textContent=this.leadErr,t.style.display="block");return}let e=this.config.bookingEndpoint||this.config.apiEndpoint.replace(/chat\.php$/,"book.php");try{let i=await(await fetch(e,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({tenant_id:this.config.id,session_id:this.sessionId,date:this.bookingForm.date,time:this.bookingForm.time,name:this.bookingForm.name.trim(),email:this.bookingForm.email.trim(),phone:this.bookingForm.phone.trim(),notes:this.bookingForm.notes.trim(),builder_id:this.bookingForm.builder_id||null})})).json();if(i.booked)this.bookingConfirmation=`${i.date} at ${i.time}`,this.addMessage("assistant",`You're booked! Tour confirmed for ${i.date} at ${i.time}. Check your email for confirmation.`),this.setView("booking-confirm");else throw new Error(i.error||"Booking failed.")}catch(t){let i=this.bodyEl.querySelector(".rc-form-err");i&&(i.textContent=t.message||"Booking failed. Please try again.",i.style.display="block")}}buildBookingConfirmView(){this.bodyEl.innerHTML="";let e=document.createElement("div");e.className="rc-form",e.innerHTML=`
      <div style="text-align:center;padding:32px 16px;">
        <span class="rc-check-icon">✓</span>
        <h3 class="rc-form-title">You're Booked!</h3>
        <p class="rc-form-desc" style="margin:0 0 8px;">${this.escAttr(this.bookingConfirmation||"")}</p>
        <p class="rc-form-desc" style="margin:0;">Check your email for confirmation.</p>
        <button class="rc-form-btn" style="width:auto;margin-top:16px;padding:10px 24px;" id="rc-back-to-chat">Back to Chat</button>
      </div>
    `,this.bodyEl.appendChild(e),e.querySelector("#rc-back-to-chat")?.addEventListener("click",()=>this.setView("chat"))}buildCardsHtml(props){if(!props||!props.length)return"";let cards=props.map(p=>{let specs=[];if(p.beds)specs.push(p.beds+" bd");if(p.baths)specs.push(p.baths+" ba");if(p.sqft)specs.push(p.sqft+" sqft");if(p.lot_size&&!p.beds)specs.push(p.lot_size+" lot");let badge=p.listing_type||"";let badgeLabel="";let badgeClass="";if(badge==="move_in_ready"){badgeLabel="Move In Ready";badgeClass="move-in-ready"}else if(badge==="under_construction"){badgeLabel="Under Construction";badgeClass="under-construction"}else if(badge==="homesite"){badgeLabel="Home Site";badgeClass="homesite"}else{badgeLabel=(p.status||"available").replace(/\b\w/g,c=>c.toUpperCase());badgeClass=(p.status||"available").toLowerCase().replace(/\s+/g,"-")};let photo=p.photo?`<img class="rc-card-img" src="${this.escAttr(p.photo)}" alt="${this.escAttr(p.address||"")}" loading="lazy">`:`<div class="rc-card-img-ph"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg></div>`;let links="";if(p.floor_plan_url)links+=`<button class="rc-card-link" data-fpurl="${this.escAttr(p.floor_plan_url)}" data-label="${this.escAttr(p.plan_name||p.address||"Floor Plan")}">Floor Plan</button>`;if(p.virtual_tour_url)links+=`<a class="rc-card-link" href="${this.escAttr(p.virtual_tour_url)}" target="_blank" rel="noopener">3D Tour</a>`;return`<div class="rc-card">${photo}<div class="rc-card-body"><div class="rc-card-price">${this.escAttr(p.price||"Price TBD")}</div><div class="rc-card-addr">${this.escAttr(p.address||p.title||"")}</div>${specs.length?`<div class="rc-card-specs">${specs.join(" \xB7 ")}</div>`:""}<div class="rc-card-badge ${badgeClass}">${this.escAttr(badgeLabel)}</div>${links?`<div class="rc-card-links">${links}</div>`:""}</div></div>`}).join("");return`<div class="rc-cards">${cards}</div>`}showLightbox(url,label){if(!this.lightboxEl){this.lightboxEl=document.createElement("div");this.lightboxEl.className="rc-lightbox";let img=document.createElement("img");img.alt=label||"Floor Plan";this.lightboxEl.appendChild(img);let btn=document.createElement("button");btn.className="rc-lb-close";btn.textContent="x";btn.addEventListener("click",()=>this.closeLightbox());this.lightboxEl.appendChild(btn);let lbl=document.createElement("div");lbl.className="rc-lb-label";lbl.textContent=label||"Floor Plan";this.lightboxEl.appendChild(lbl);this.lightboxEl.addEventListener("click",(e)=>{if(e.target===this.lightboxEl)this.closeLightbox()});this.windowEl.appendChild(this.lightboxEl)}this.lightboxEl.querySelector("img").src=url;this.lightboxEl.querySelector(".rc-lb-label").textContent=label||"Floor Plan";requestAnimationFrame(()=>this.lightboxEl.classList.add("rc-lb-open"))}closeLightbox(){this.lightboxEl&&this.lightboxEl.classList.remove("rc-lb-open")}escAttr(e){return e.replace(/&/g,"&amp;").replace(/"/g,"&quot;").replace(/</g,"&lt;").replace(/>/g,"&gt;")}destroy(){let e=this.root.host;e&&e.parentNode&&e.parentNode.removeChild(e)}};function g(){let n=document.currentScript||document.querySelector("script[data-robchat-id]");if(!n){console.warn("[HWChat] No script tag with data-robchat-id found.");return}let e=n.getAttribute("data-robchat-id");if(!e){console.warn("[HWChat] data-robchat-id attribute is empty.");return}let t=n.getAttribute("data-api-base")||new URL(n.getAttribute("src")||"",window.location.href).origin;fetch(`${t}/api/tenant-config.php?id=${encodeURIComponent(e)}`).then(i=>{if(!i.ok)throw new Error(`Tenant config fetch failed: ${i.status}`);return i.json()}).then(i=>{i.id=i.id||e,i.apiEndpoint=i.apiEndpoint||`${t}/api/chat.php`,i.poweredByUrl=i.poweredByUrl||"https://www.hillwoodcommunities.com";let a=new r(i);window.__robchat=a}).catch(i=>{console.error("[HWChat] Failed to initialize:",i);let a={id:e,name:n.getAttribute("data-name")||"AI Assistant",greeting:n.getAttribute("data-greeting")||"Hey! How can I help you today?",accentColor:n.getAttribute("data-accent")||"#3B7DD8",apiEndpoint:n.getAttribute("data-api")||`${t}/api/chat.php`,poweredByUrl:"https://www.hillwoodcommunities.com",quickReplies:n.getAttribute("data-quick-replies")?JSON.parse(n.getAttribute("data-quick-replies")):void 0},o=new r(a);window.__robchat=o})}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",g):g();return k(B);})();
