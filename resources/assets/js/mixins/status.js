export default {

    data() {
        return {
            status: {
                text: '',
                error: '',
                active: false,
            }
        };
    },

    computed: {
        isStatusActive() {
            return this.status.text || this.status.error || this.status.active;
        }
    },
    created: function () {
    },
    methods: {
        statusUpdate: function (err, data) {
            if (err ){
                if (err.body.error) {
                    this.status.error = 'Error'
                        + err.status
                        + ' - '
                        + 'Neem een print screen en neem contact op met de servicedesk.';
                }
                else if (err.body.message) {
                    this.modal.error = err.body.message;
                }
            }
            else if (data) {
                this.status.text = data.text || '';
                this.status.active = data.active;
            }
            else {
                this.status.text = 'Er is een onbekende fout opgetreden.';
            }
        },
        statusReset: function () {
            this.$set(this.status, 'active', false);
            this.$set(this.status, 'text', null);
            this.$set(this.status, 'error', null);
        }
    }
}

