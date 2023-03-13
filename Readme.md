# Wegmeister.DatabaseStorage

This package adds the ability to store form submissions into a database and export the stored data as xlsx, xls, ods, csv or html.

## Installation

To install the package simply run

```
composer require wegmeister/databasestorage
```

## Usage

You can add the DatabaseStorage Finisher in the following ways:

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
      identifier: 'my-form-data'
```

### Add DatabaseStorage using the Neos Form Builder

You can also use the DatabaseStorage with the [Neos.Form.Builder](https://github.com/neos/form-builder).
You should be able to simply add DatabaseStorage as a finisher to your form.

Don't forget to set a (unique) `identifier`!

### Add DatabaseStorage using a Fusion Form

You can also use the DatabaseStorage [Neos.Fusion.Form](https://github.com/neos/fusion-form) action.

Add the following configuration to your form action definition:

    databaseStorage {
        type = '\\Wegmeister\\DatabaseStorage\\FusionForm\\Runtime\\Action\\DatabaseStorageAction'
        options {
            identifier = 'identifier-in-backend'
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

## Cleanup command

The package comes with a cleanup command to delete data older than a date interval you can define in your settings.
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
      storageIdentifier2:
          dateInterval: "P1Y"
```
