import Vue from 'vue';
import ToggleButton from 'vue-js-toggle-button';

import App from './components/App';

Vue.use(ToggleButton);

new Vue({
    el: '#app',
    render(h) {
        return h(App, {
            props: {
                dataServices: JSON.parse(this.$el.dataset.services),
                action: this.$el.dataset.action,
            }
        });
    }
});