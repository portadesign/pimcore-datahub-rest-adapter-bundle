[![Software License](https://img.shields.io/badge/license-GPLv3-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Latest Stable Version](https://img.shields.io/packagist/v/w/simple-rest-adapter-bundle.svg?style=flat-square)](https://packagist.org/packages/ci-hub/simple-rest-adapter-bundle)

This bundle adds a REST API endpoints to [Pimcore DataHub](https://github.com/pimcore/data-hub)
for Assets and DataObjects. All exposed data can be configured, is indexed in Elasticsearch and delivered from there
for better query performance and scalability.

Therefore, it can be used to connect Pimcore to other systems or to connect Front-End applications.

## Support

We are the owner of The Pimcore Connector Bundle due to our commitment to the Open Source community and the amazing users of Pimcore Systems around the world. An as any OpenSource Project the support if provided by the community for free.
 
Here is the GitHub Community Board if you like to collaborate with the community on how to improve or enhance the Pimcore Connector Bundle.
 
In addition, if you consider commercial Pimcore Implementation we are ready to help with Implementation and adapting it to your Corporate needs. We do offer commercial support as PORTA DESIGN for specific commercial Pimcore implementations. To get more details about the process and maybe involved cost, please contact us: development@portadesign.cz, [portadesign.cz/pimcore](https://portadesign.cz/pimcore).
 
So you can choose what works best for you.

## Features in a nutshell
* Configure a schema and expose data like with other DataHub adapters via Drag & Drop.
* All data gets indexed in Elasticsearch indices and delivered from there (no additional load on the database
  when accessing data via REST endpoints).
* Endpoint documentation and test via Swagger UI.
* Available endpoints:
  * **tree-items**: Method to load all elements of a tree level with additional support for:
    * paging
    * filtering
    * fulltext search
    * ordering
    * aggregations – provide possible values for fields to create filters
  * **search**: Method to search for elements, returns elements of all types (no folder structures)
    with additional support for:
    * paging
    * filtering
    * fulltext search
    * ordering
    * aggregations – provide possible values for fields to create filters
  * **get-element**: Method to get one single element by type and ID.
  * **add-asset**: Method to create and replace asset
  * **download-asset**: Method to download binary file
  * **lock-asset**: Method to lock asset by current user
  * **unlock-asset**: Method to unlock asset by current user
* Endpoint security via bearer token that has to be sent as header with every request.

![Schema Configuration](docs/images/schema.png "Schema Configuration")
![Swagger UI](docs/images/swagger_ui.png "Swagger UI")

## Further Information
* [Installation & Bundle Configuration](docs/00-installation-configuration.md)
* [User authorization](docs/00-user-authorization.md)
* [Endpoint Configuration Details](docs/01-endpoint-configuration.md)
* [Indexing Details](docs/02-indexing.md)
* [Development](docs/04-development.md)

## License
**PORTA DESIGN s.r.o.**\
[portadesign.cz](portadesign.cz), development@portadesign.cz\
Copyright © 2026 PORTA DESIGN s.r.o. All rights reserved.

**Brand Oriented**, Okopowa 33/26, 01-059 Warszawa, Polska  
[brandoriented.pl](https://brandoriented.pl), biuro@brandoriented.pl  
Copyright © 2023 Brand Oriented. All rights reserved.

**CI HUB GmbH**, Benkertstrasse 4, 14467 Potsdam, Germany  
[ci-hub.com](https://ci-hub.com), info@ci-hub.com  
Copyright © 2021 CI HUB GmbH. All rights reserved.

For licensing details please visit [LICENSE.md](LICENSE.md)
