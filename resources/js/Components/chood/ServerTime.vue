<template>
    Current database time is: {{ thedata.now }}
</template>

<script>
export default {
    data() {
        return {
            thedata: []
        };
    },
    created() {
        this.fetchServerTime();
        setInterval(this.fetchServerTime, 5000); // Fetch time every 5 seconds
    },
    methods: {
        fetchServerTime() {
            fetch('/current-time')
                .then(response => response.json())
                .then(data => {
                    console.log(data.now);
                    this.thedata = data;
                })
                .catch(error => {
                    console.error('Error fetching server time:', error);
                });
        }
    }
};
</script>
