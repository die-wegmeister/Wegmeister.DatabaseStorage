# Wegmeister.DatabaseStorage

This package adds the ability to store form submissions into a database and export the stored data as xlsx, xls, ods, csv or html.

## Installation

To install the package simply run

```
composer require wegmeister/databasestorage
```

## Usage

> :exclamation: The DatabaseStorage stores your data as JSON. Therefore only the labels of the first entry can be used for the headline/export. Keep that in mind and try to avoid changing your forms later on. Whenever you add a field **after** someone already entered some data, the new field would not exist in the headline row of the exported table :exclamation:

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
    identifier: 'Wegmeister.Database:DatabaseStorageFinisher'
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
```
