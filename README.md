# Simple Magento 2 search with ElasticSearch

## Installation
You need to install the analysis-icu plugin for ElasticSearch.

You have to lock the Elasticsearch configurations:
```
php bin/magento config:set catalog/search/elasticsearch7_server_hostname <elasticsearch_hostname>
php bin/magento config:set catalog/search/elasticsearch7_server_port <elasticsearch_port>
php bin/magento config:set catalog/search/elasticsearch7_index_prefix <elasticsearch_index_prefix>
php bin/magento config:set catalog/search/elasticsearch7_enable_auth <elasticsearch_enable_auth>
php bin/magento config:set catalog/search/elasticsearch7_username <elasticsearch_username>
php bin/magento config:set catalog/search/elasticsearch7_password <elasticsearch_password>
```

Copy file `es.php` to your Magento `pub` directory.
```
cp vendor/ph2m/magento2-elasticsearch/es.php pub/
```

Update your `Magento_Theme/templates/html/header.phtml` file to include the search form.

```
<input name="es_search"
   id="es_search"
   type="text"
   placeholder="<?php echo __('Search a product or a brand') ?>"
   class="!pr-[84px] w-full bg-gray-very-light-warm !h-18 text-dark placeholder:text-xxs placeholder:leading-5 focus:placeholder:font-normal !text-xl !leading-6.75"
   @keyup="runSearch()" />
<div class="absolute bottom-0 translate-y-full z-50 left-0 bg-white w-full grid grid-cols-4 gap-5 p-5 border" x-cloak x-show="results.length > 0">
    <template x-for="result in results" :key="result.sku">
        <a :href="result.url_key" class="flex items-center gap-3">
            <img :src="result.image" :alt="result.name_to_display" width="80" height="80" />
            <div class="flex flex-col">
                <span x-text="result.name_to_display"></span>
                <span class="text-xs" x-text="result.brand_value"></span>
            </div>
        </a>
    </template>
</div>
```

And at the end of the file add the following script:
```
<script>
    function initSearch() {
        return {
            results: [],
            runSearch() {
                if (this.$el.value.length < 3) {
                    this.results = [];
                    return;
                }

                const url = BASE_URL + 'es.php?q=' + this.$el.value + '&store=' + CURRENT_STORE_ID;

                fetch(url, {
                    headers: { "content-type": "application/json" },
                    method: "GET",
                }).then(response => {
                    if (response.ok) {
                        return response.json();
                    }
                }).then(result => {
                    this.results = result;
                });
            }
        }
    }
</script>
```


## Only for Hyvä
This module will only work with [Hyvä](https://www.hyva.io/) out of the box.
