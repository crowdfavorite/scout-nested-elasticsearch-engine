This Engine supports the ElasticSearch driver. It differs from the default
Scout ES engine as it provides support for nested document where clauses.

#Installation

Via Composer: 

Add the repository definition to your `composer.json` file: 

``` 
     "repositories": [
     {
       "type": "vcs",
       "url": "https://github.com/crowdfavorite/scout-nested-elasticsearch-engine"
     }
    ],
    "require" ...
````


`composer require crowdfavorite/scout-nested-elasticsearch-engine`
`composer update -v`

Add the service provider to `config/app.php` :

`CrowdFavorite\Scout\NestedElasticSearchScoutServiceProvider::class,`

Set `SCOUT_DRIVE=nestedelasticsearch` in your .env file

#Usage

`Contacts::search('John')->where('company.company_id', 1)->get()`
