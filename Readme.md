# Wegmeister.DatabaseStorage

This package adds the ability to store values of a form (or other input) into database and export the stored data as xlsx.

To add it as a form finisher you have two options:


## Use DatabaseStorage in an `old fashioned` yaml definition

Add the DatabaseStorage a finisher in your form definition/yaml file.

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


## Use DatabaseStorage with the new Neos Form-Builder

You can also use the DatabseStorage with the new [Neos.Form.Builder](https://github.com/neos/form-builder). 
You should be able to simply add DatabaseStorage as a finisher to your formular. 

Don't forget to set an (unique) `identifier`!
