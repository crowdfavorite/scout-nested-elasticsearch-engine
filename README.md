This Engine supports the ElasticSearch driver. It differs from the default
Scout ES engine as it provides support for nested document where clauses.


Usage Ex:

``Contacts::search('John')->where('company.company_id', 1)->get()`
