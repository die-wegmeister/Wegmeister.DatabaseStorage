# Wegmeister.DatabaseStorage

This package adds the ability to store values of a form (or other input) into database and export the stored data as xlsx, xls, ods, csv or html.

## Installation

To install the package simply run

```
composer require wegmeister/databasestorage
```

## Usage

> :exclamation: The DatabaseStorage stores your data as JSON. Therefore only the Labels of the first entry can be used for the headline/export. Keep that in mind and try to avoid changing your forms later on. Whenever you add a now field **after** someone already entered some data, the new field would not exist in the headline row of the exported table :exclamation:

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
    identifier: 'Wegmeister.Database:DatabaseStorage'
    options:
      # The identifier is used to group your data in the database.
      # You should avoid using the same identifier twice or your data could become a little messed up.
      identifier: 'my-form-data'
```


## Add DatabaseStorage using the new Neos Form-Builder

You can also use the DatabseStorage with the new [Neos.Form.Builder](https://github.com/neos/form-builder).
You should be able to simply add DatabaseStorage as a finisher to your formular.

Don't forget to set an (unique) `identifier`!


## ToDos

- [ ] Add translations
- [ ] Add the ability to remove a single entry
- [x] Fix missing icons in Neos 4.0
- [x] Update package to work with Neos 4.0
