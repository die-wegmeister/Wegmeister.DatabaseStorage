# Wegmeister.DatabaseStorage

This package adds the ability to store form submissions into a database and export the stored data as xlsx, xls, ods, csv or html.

## Installation

To install the package simply run

```
composer require wegmeister/databasestorage
```

## Usage

You can add the DatabaseStorage Finisher in the following ways.

> [!NOTE]
> Since version `2.1.0` the DatabaseStorage Finisher will add a new form value
> `databaseStorageIdentifier` that can be used in further finishers.
> This value is the identifier of the database entry and can be helpful if you 
> need to reference the entry in other finishers.

### Add DatabaseStorage using YAML definitions

Add the DatabaseStorage a finisher in your form definition/yaml file:

```yaml
type: 'Neos.Form:Form'
identifier: 'some-identifier'
label: 'My form'
renderables:
  # Your renderables / form fields go here

finishers:
  -
    identifier: 'Wegmeister.DatabaseStorage:DatabaseStorageFinisher'
    options:
      # The identifier is used to group your data in the database.
      # You should avoid using the same identifier twice or your data could become a little messed up.
      storageIdentifier: 'my-form-data'
```

### Add DatabaseStorage using the Neos Form Builder

You can also use the DatabaseStorage with the [Neos.Form.Builder](https://github.com/neos/form-builder).
You should be able to simply add DatabaseStorage as a finisher to your form.

Don't forget to set a (unique) `storageIdentifier`!

### Add DatabaseStorage using a Fusion Form

You can also use the DatabaseStorage [Neos.Fusion.Form](https://github.com/neos/fusion-form) action.

Add the following configuration to your form action definition:

    databaseStorage {
        type = '\\Wegmeister\\DatabaseStorage\\FusionForm\\Runtime\\Action\\DatabaseStorageAction'
        options {
            storageIdentifier = 'identifier-in-backend'
            formValues = ${data}
        }
    }

## Available settings

The following settings are available and can be overridden by your Settings.yaml:

```yaml

Wegmeister:
  DatabaseStorage:
    # Creator name of the exported files
    creator: 'die wegmeister gmbh'
    # Title for the exported files
    title: 'Database Export'
    # Subject for the exported files
    subject: 'Database Export'
    # DateTime format if the datetime is included in the export
    datetimeFormat: 'Y-m-d H:i:s'
    # Number of entries per page on the identifier list view
    itemsPerPage: 20
    # Array list item prefix on the identifier list view
    listPrefix: '-'
    # Form element types that should not be stored by the finisher (for Node-based forms)
    nodeTypesIgnoredInFinisher:
      - 'Neos.Form.Builder:Section'
      - 'Neos.Form.Builder:StaticText'
    # Form element types that should not be part of the export (for Node-based forms)
    nodeTypesIgnoredInExport:
      - 'Neos.Form.Builder:Section'
      - 'Neos.Form.Builder:StaticText'
      - 'Neos.Form.Builder:Password'
      - 'Neos.Form.Builder:PasswordWithConfirmation'
```

## Cleanup commands

The package comes with cleanup commands to delete data older than a date interval you can define in your settings.
You can run the command manually or use a cron job.

Add storages you wish to be cleaned up and define how long the data of each storage should be stored:

```yaml

Wegmeister:
  DatabaseStorage:
    cleanup:
      # Add storage identifier you wish to be cleaned up
      storageIdentifier1:
        # Define how long the data should be stored as date interval
        # https://www.php.net/manual/en/class.dateinterval.php
        dateInterval: "P6M"
        removeFiles: true
      storageIdentifier2:
          dateInterval: "P1Y"
          removeFiles: false
```

Run the cleanup command for the configured storages:

```
./flow databasestorage:cleanupconfiguredstorages
```

You can also run a cleanup command for all existing storages. The command comes with parameters:

| Parameter Name              | Data Type | Description                                                                                                                                                           |
|-----------------------------|-----------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| date-interval               | string    | Defines which data should be deleted. We use the PHP DateInterval format. You can find more information [here](https://www.php.net/manual/en/class.dateinterval.php). |
| include-configured-storages | boolean   | If you have configured storages in your settings, you can skip them with this parameter.                                                                              |
| remove-files                | boolean   | The PersistentResource that is potentially attached to the database storage entry will be removed as well.                                                            |



```
./flow databasestorage:cleanupallstorages --date-interval=P1M --remove-files
```

