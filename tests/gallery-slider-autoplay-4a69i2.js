/** Isolated slider interactions, clock control, accessibility, single-image fallback. */
'use strict';
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const code = fs.readFileSync(path.join(__dirname,'../modules/gallery/assets/gallery-slider.js'),'utf8');
const makeButton = () => ({attrs:{},dataset:{},events:{},addEventListener(t,f){this.events[t]=f;},setAttribute(k,v){this.attrs[k]=v;},click(){this.events.click();}});
const slides = Array.from({length:3}, () => ({hidden:false,inert:false}));
const previous=makeButton(),next=makeButton(),play=makeButton(),controls={hidden:true},counter={textContent:''};
const handlers={}, gallery={dataset:{galleryAutoplay:'1',galleryInterval:'7'},classList:{add(x){this.added=x;}},
 querySelectorAll(s){assert.equal(s,':scope > .gallery__item');return slides;},
 querySelector(s){return {'[data-gallery-controls]':controls,'[data-gallery-previous]':previous,'[data-gallery-next]':next,'[data-gallery-counter]':counter,'[data-gallery-play]':play}[s];},
 addEventListener(t,f){handlers[t]=f;},contains(){return false;}};
let scheduled=[],disposed=[];
const document={hidden:false,activeElement:null,readyState:'complete',querySelectorAll(s){assert.equal(s,'[data-gallery-slider]');return [gallery];},addEventListener(t,f){handlers['document:'+t]=f;}};
const context={document,window:{setTimeout(fn,delay){ assert.equal(delay,7000);const id=scheduled.length+1;scheduled.push({id,fn});return id;}},clearTimeout(id){disposed.push(id);}};
vm.runInNewContext(code,context,{timeout:1000});
assert.equal(counter.textContent,'1 / 3'); assert.equal(scheduled.length,1);
let fn=scheduled.at(-1).fn;fn();assert.equal(counter.textContent,'2 / 3');
handlers.mouseenter();const before=scheduled.length;fn=scheduled.at(-1).fn;assert.equal(disposed.length>0,true);
handlers.mouseleave();assert.ok(scheduled.length>before);
play.click();assert.equal(play.attrs['aria-pressed'],'false');let count=scheduled.length;
play.click();assert.equal(play.attrs['aria-pressed'],'true');assert.equal(scheduled.length,count+1);
handlers.focusin(); count=scheduled.length; assert.ok(disposed.length>=2);
handlers.focusout();assert.equal(scheduled.length,count+1);
document.hidden=true;handlers['document:visibilitychange']();count=scheduled.length;
document.hidden=false;handlers['document:visibilitychange']();assert.equal(scheduled.length,count+1);
next.click();assert.equal(counter.textContent,'3 / 3');next.click();assert.equal(counter.textContent,'1 / 3');
let prevented=false;handlers.keydown({key:'ArrowLeft',altKey:false,ctrlKey:false,metaKey:false,shiftKey:false,preventDefault(){prevented=true;}});assert.ok(prevented);assert.equal(counter.textContent,'3 / 3');
handlers.touchstart({touches:[{clientX:170,clientY:40}]});handlers.touchend({changedTouches:[{clientX:20,clientY:45}]});assert.equal(counter.textContent,'1 / 3');
assert.equal(gallery.dataset.galleryInitialized,'1'); assert.equal(gallery.classList.added,'gallery--enhanced');
vm.runInNewContext(code,context,{timeout:1000});assert.equal(counter.textContent,'1 / 3');
console.log('Gallery Slider autoplay i2 smoke: 19 assertions passed');
