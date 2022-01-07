# Wegmeister.DatabaseStorage

This package adds the ability to store values of a form (or other input) into the database and export the stored data as xlsx, xls, ods, csv or html.

## Installation

To install the package simply run

```
composer require wegmeister/databasestorage
```

## Usage

You can add the DatabaseStorage Finisher in two ways:

### Add DatabaseStorage using yaml definitions

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

You can also use the DatabseStorage with the [Neos.Form.Builder](https://github.com/neos/form-builder) package.
You can simply add DatabaseStorage as a finisher to your form.

Don't forget to set a (unique) `identifier`!

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
    # Form element types that should not be part of the export
    nodeTypesIgnoredInExport:
      - 'Neos.Form.Builder:Section'
      - 'Neos.Form.Builder:StaticText'
      - 'Neos.Form.Builder:Password'
      - 'Neos.Form.Builder:PasswordWithConfirmation'
      - 'Neos.Form.Builder:HiddenField'
```
