import jQuery from 'jquery';
import select2 from 'select2/dist/js/select2.full.js';

window.$ = window.jQuery = jQuery;

if (typeof select2 === 'function') {
    select2(window, jQuery);
}

export default jQuery;
