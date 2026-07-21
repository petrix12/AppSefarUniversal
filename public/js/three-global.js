import * as THREE from './three.js';

window.THREE = THREE;
window.dispatchEvent(new CustomEvent('sefar:three-ready', { detail: { THREE } }));
window.sefarThreeReady = Promise.resolve(THREE);
