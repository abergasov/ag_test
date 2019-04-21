let app = new Vue({
    delimiters: ['${', '}'],
    el: '#fb_app',
    data: {
        errorMsg: '',
        confirmMsg: '',
        pending_request: false,
        adAccData: {}
    },
    mounted: function () {
        this.getCommonINfo();
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
                }
            );
        },

        changeSpendCup: function () {
            const self = this;
            this.callApiRequest(
                'api/facebook/ads/manager/acc_spend_cup',
                function () {
                    self.confirmMsg = 'spend cup limit changed';
                    setTimeout(function () {
                        self.confirmMsg = '';
                    },3000);
                },
                'POST',
                {
                    'act_id': this.adAccData.id,
                    'amount': this.adAccData.spend_cap
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