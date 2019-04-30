let app = new Vue({
    delimiters: ['${', '}'],
    el: '#fb_app',
    data: {
        errorMsg: '',
        confirmMsg: '',
        pending_request: false,
        adAccData: {},
        newSpendCup: 0,
        headers: new Headers()
    },
    mounted: function () {
        let tkn = document.getElementById('token');
        if (tkn !== null) {
            this.headers.append("x-auth-data", tkn.value);
        }

        this.getCommonINfo();
    },
    watch: {
        newSpendCup: function () {
            this.newSpendCupCents = parseInt(this.newSpendCup - this.adAccData.amount_spent)*100;
        }
    },
    methods: {
        notEmptyObject(obj){
            return typeof obj !== 'undefined' && Object.keys(obj).length > 0
        },
        getCommonINfo: function () {
            const self = this;
            this.callApiRequest(
                '/api/facebook/ads/manager/info',
                function (response) {
                    self.adAccData = response.adaccounts;

                    //convert spend amount to js float
                    self.adAccData.amount_spent = parseFloat(self.adAccData.amount_spent.replace(',', '.'));
                    //convert spend amount from cents to USD
                    self.adAccData.spend_cap = parseInt(self.adAccData.spend_cap) / 100;
                    //set suggested value of spend cup
                    self.newSpendCup = self.adAccData.spend_cap - self.adAccData.amount_spent;
                    self.newSpendCupCents = (self.adAccData.spend_cap - self.adAccData.amount_spent)*100;
                }
            );
        },

        changeSpendCup: function () {
            const self = this;
            this.callApiRequest(
                'api/facebook/ads/manager/acc_spend_cup',
                function () {
                    self.confirmMsg = 'spend cup limit changed';
                    self.adAccData.spend_cap = self.newSpendCupCents/100
                    setTimeout(function () {
                        self.confirmMsg = '';
                    },3000);
                },
                'POST',
                {
                    'act_id': this.adAccData.id,
                    'amount': this.newSpendCupCents
                })
        },

        changeAdsetAmount: function (adId) {
            const self = this;
            let value = this.adAccData.adsets.find(x => x.id === adId);
            this.callApiRequest(
                '/api/facebook/ads/manager/adset_limit',
                function () {
                    self.confirmMsg = 'limit changed';
                    setTimeout(function () {
                        self.confirmMsg = '';
                    },3000);
                },
                'POST',
                {
                    'act_id': this.adAccData.id,
                    'ad_set_id': adId,
                    'amount': value.daily_budget
                })
        },

        callApiRequest: function (url, onSuccess, method, data) {
            const self = this;
            self.pending_request = true;
            self.errorMsg = '';

            let dataInit = {
                headers: this.headers,
                method: method || 'GET',
            };
            if (dataInit.method !== 'GET') {
                dataInit.body = JSON.stringify(data)
            }
            fetch(url, dataInit)
                .then((response) => {
                    self.pending_request = false;
                    if (response.status !== 200) {
                        self.errorMsg = 'Looks like there was a problem. Status Code: ' + response.status;
                        return;
                    }
                    if (typeof onSuccess === "function") {
                        response.json().then(function(data) {
                            if (data.ok) {
                                onSuccess(data);
                            } else {
                                self.errorMsg = data.error;
                            }
                        });
                    }
                });
        }
    }
});