# Development

# How to install a bundle inside Pimcore app.

## SimpleRESTAdapterBundle
Choose your directories and use them in the following examples. 

Clone repository (fork) to the `/var/pimcore-datahub-rest-adapter-bundle` directory.

    git clone https://github.com/PortaDesign/pimcore-datahub-rest-adapter-bundle .

Add the following as the first elements in the `repositories` section of the `composer.json` file.
```
    {
      "type": "path",
      "url": "./var/pimcore-datahub-rest-adapter-bundle",
      "options": {
        "symlink": true
      }
    }
```

Install or switch to the local version. After saving `composer.json`, run from the project root:

    composer update portadesign/pimcore-datahub-rest-adapter-bundle

Or, if the package is not yet installed:

    composer require portadesign/pimcore-datahub-rest-adapter-bundle "dev-main"
