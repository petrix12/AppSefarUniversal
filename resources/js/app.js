require('./bootstrap');

import Alpine from 'alpinejs'
window.Alpine = Alpine

Alpine.start()

//const Swal = require('sweetalert2');
window.Swal = require('sweetalert2');   /* Se trae el sweetalert2 de  node_modules */
