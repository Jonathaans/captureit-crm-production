'use strict';
// Run with Node. These are source/behaviour tests, not a Laravel/browser benchmark.
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const crypto = require('crypto');
const assert = require('assert/strict');
const base = path.resolve(__dirname, '..');
const chat = fs.readFileSync(path.join(base, 'chat.blade.php'), 'utf8');
const widget = fs.readFileSync(path.join(base, 'widget.blade.php'), 'utf8');
const scripts = source => [...source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)].map(x => x[1]);

function substituteBlade(source) {
  // Substitute server-generated scalar data only for JS parsing, not execution.
  let s = source.replace(/\{\{--[\s\S]*?--\}\}/g, '').replace(/@php[\s\S]*?@endphp/g, '');
  for (let match; (match = /@json\s*\(/.exec(s));) {
    let i = match.index + match[0].length, depth = 1, quote = null;
    for (; i < s.length && depth; i++) {
      const c = s[i];
      if (quote) {
        if (c === '\\') i++;
        else if (c === quote) quote = null;
      } else if (c === '"' || c === "'") quote = c;
      else if (c === '(') depth++;
      else if (c === ')') depth--;
    }
    assert.equal(depth, 0, 'Unbalanced Blade JSON directive');
    s = s.slice(0, match.index) + '0' + s.slice(i);
  }
  return s.replace(/\{\{[\s\S]*?\}\}/g, '0');
}

function events(extra = {}) {
  const handlers = new Map();
  return Object.assign({
    addEventListener(name, callback) {
      if (!handlers.has(name)) handlers.set(name, []);
      handlers.get(name).push(callback);
    },
    emit(name, event = {}) { for (const callback of handlers.get(name) || []) callback(event); },
  }, extra);
}
const deferred = () => {
  let resolve, reject;
  const promise = new Promise((a,b) => { resolve = a; reject = b; });
  return {promise, resolve, reject};
};
const settle = async () => { for (let i=0; i<8; i++) await Promise.resolve(); };

async function testMessageSync() {
  const start = chat.indexOf('let messageSyncInFlight = null;');
  const end = chat.indexOf('                form.addEventListener(', start);
  assert(start > 0 && end > start);
  let calls = 0;
  const gates = [];
  const context = vm.createContext({Promise, fetchMessages: () => {
    calls++; const gate = deferred(); gates.push(gate); return gate.promise;
  }});
  vm.runInContext(chat.slice(start, end) + '\nglobalThis.sync = pollMessages;', context);
  const first = context.sync();
  for(let i=0; i<20; i++) assert.equal(context.sync(), first);
  await settle();
  assert.equal(calls, 1, 'Burst before network must produce one fetch');
  for(let i=0; i<20; i++) context.sync();
  assert.equal(calls, 1, 'No overlapping fetches');
  gates[0].resolve(); await settle();
  assert.equal(calls, 2, 'Event during fetch must trigger one follow-up');
  gates[1].resolve(); await first;
  const failure = context.sync(); await settle();
  const caught = assert.rejects(failure, /test failure/);
  gates[2].reject(Error('test failure')); await caught;
  const retry = context.sync(); await settle();
  assert.equal(calls, 4, 'Failure must release the lock');
  gates[3].resolve(); await retry;
}

async function testScroll() {
  const script = scripts(chat).find(s => s.includes('const bootChatNewestV13'));
  assert(script && !script.includes('setInterval') && !script.includes('setTimeout'));
  const raf = new Map(); let nextId = 0, scrollWrites = 0;
  const root = events({dataset:{}, style:{}, parentElement:null, scrollHeight:1000, clientHeight:200});
  let top = 0;
  Object.defineProperty(root, 'scrollTop', {get:()=>top, set:x=>{ top=Math.max(0,Math.min(x,root.scrollHeight-root.clientHeight)); scrollWrites++; }});
  const message = {scrollIntoView(){}};
  const stack = {querySelectorAll(){ return [message]; }};
  const form = {};
  const fonts = deferred();
  const document = events({readyState:'complete', body:{}, hidden:false, fonts:{ready:fonts.promise}, getElementById(id) {
    return {'crm-chat-messages':root, 'crm-chat-message-stack':stack, 'crm-chat-bottom':message, 'crm-chat-send-form':form}[id];
  }});
  const window = events({requestAnimationFrame(fn){ const id=++nextId; raf.set(id,fn); return id; }, getComputedStyle(){return {overflowY:'auto'};}});
  const mutations = [], resizes = [];
  const context = vm.createContext({window,document,history:{scrollRestoration:'auto'},
    MutationObserver: class { constructor(fn){mutations.push(fn);} observe(){} },
    ResizeObserver: class { constructor(fn){resizes.push(fn);} observe(){} },
  });
  window.ResizeObserver = context.ResizeObserver;
  vm.runInContext(script, context);
  const flush = () => { const batch=[...raf.values()]; raf.clear(); for(const fn of batch) fn(); };
  for(let i=0;i<30;i++){ mutations[0](); resizes[0](); }
  assert.equal(raf.size,1,'Burst of DOM changes is one scroll frame');
  flush(); assert.equal(top,800); assert.equal(raf.size,0,'Idle has no recurring scroll work');
  root.emit('wheel',{deltaY:-10}); root.scrollTop=200;
  const writesBefore=scrollWrites;
  mutations[0](); resizes[0](); window.emit('load'); root.emit('load');
  fonts.resolve(); await settle(); flush();
  assert.equal(top,200,'Late layout/load events must respect reading older messages');
  assert.equal(scrollWrites,writesBefore);
  document.emit('submit',{target:form}); assert.equal(raf.size,1);
  flush(); assert.equal(top,800,'Sending a message follows the bottom');
  root.emit('touchstart',{touches:[{clientY:100}]});
  root.emit('touchmove',{touches:[{clientY:120}]}); root.scrollTop=300;
  mutations[0](); flush(); assert.equal(top,300,'Touch scrolling stays readable');
  window.crmChatGoNewest(); flush(); assert.equal(top,800);
}

async function testPresence() {
  const script=scripts(widget).find(s=>s.includes('const sendPresence ='));
  assert(script && !widget.includes('__crmGlobalPresenceHeartbeatV331'));
  assert(!chat.includes('const heartbeat ='));
  const timers=[], calls=[], gates=[];
  const document=events({visibilityState:'visible',getElementById(){return {dataset:{heartbeatUrl:'/heartbeat',csrf:'test'}};}});
  const window=events({location:{pathname:'/admin/internal-chat'},setInterval(fn,delay){timers.push({fn,delay});}});
  const context=vm.createContext({document,window,Date,JSON,Math,fetch(url,options){calls.push({url,options});const d=deferred();gates.push(d);return d.promise;}});
  vm.runInContext(script,context);
  assert.equal(calls.length,1); assert.equal(timers.length,1); assert.equal(timers[0].delay,15000);
  const payload=JSON.parse(calls[0].options.body);
  assert.equal(payload.idle_seconds,0); assert.equal(payload.in_chat,true);
  for(let i=0;i<20;i++)timers[0].fn(); assert.equal(calls.length,1,'Only one heartbeat in flight');
  gates[0].resolve({ok:true}); await settle();
  document.visibilityState='hidden'; timers[0].fn(); assert.equal(calls.length,1);
  document.visibilityState='visible'; timers[0].fn(); assert.equal(calls.length,2);
  gates[1].reject(Error('offline')); await settle();
  timers[0].fn(); assert.equal(calls.length,3,'Presence error does not lock later requests');
  gates[2].resolve({ok:true}); await settle();
}

(async()=>{
  const manifest=JSON.parse(fs.readFileSync(path.join(base,'manifest.json'),'utf8'));
  for(const f of manifest.files){
    const source=fs.readFileSync(path.join(base,f.name),'utf8');
    const hash=crypto.createHash('sha256').update(source.replace(/\r\n/g,'\n')).digest('hex');
    assert.equal(hash,f.after_sha256);
    assert.notEqual(f.before_sha256,f.after_sha256);
  }
  let parsed=0;
  for(const [name,source] of [['chat',chat],['widget',widget]]){
    for(const script of scripts(source)){
      new vm.Script(substituteBlade(script),{filename:name+'-script-'+(++parsed)});
    }
  }
  process.stdout.write('PASS: '+parsed+' inline JavaScript blocks parsed (Blade data substituted).\n');
  await testMessageSync(); process.stdout.write('PASS: message bursts, in-flight events, failure recovery.\n');
  await testScroll(); process.stdout.write('PASS: bounded scroll scheduling and manual reading position.\n');
  await testPresence(); process.stdout.write('PASS: one activity heartbeat, visibility and failure recovery.\n');
  process.stdout.write('PHP installer, Blade compilation and live CRM performance are NOT tested here.\n');
})().catch(error=>{console.error(error);process.exitCode=1;});
