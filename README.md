# EditCountNeue

The **EditCountNeue** extension allows wikis to display the number of edits of a user, via a special page or a parser function. It is inspired by [Editcount](https://www.mediawiki.org/wiki/Extension:Editcount), and is rewritten to have more functionality and better support for newer versions of MediaWiki. **EditCountNeue is a replacement of Editcount, please disable or remove Editcount before enabling EditCountNeue**.

## Installation

* Download from the [release page](https://github.com/AlPha5130/mediawiki-extensions-EditCountNeue/releases), extract the archive into a directory called `EditCountNeue` in your `extensions/` folder.
* Add the following code at the bottom of your `LocalSettings.php`:

``` php
wfLoadExtension( 'EditCountNeue' );
```

* Done - Navigate to Special:Version on your wiki to verify that the extension is successfully installed.

## Usage

### Special page

EditCountNeue adds a special page called `Special:EditCount` under `users` group. Select a user and click to see the the number of edits of the user. It can also be accessed from `Special:EditCount/<username>`.

### Parser function

EditCountNeue also adds a parser function to retrieve the number of edits of a user. The syntax is:

``` txt
{{#editcount: <username> [ | <namespace1> [ | <namespace2> ... ] ] }}
```

`username` is required and is the name of the target user.

`namespace1`, `namespace2` and so on are all optional and are the namespace names or namespace numbers of your need. If specified, the function returns the edit number of the specified namespaces. If omitted, returns the edit number of all namespaces.

An invalid username causes the function to return 0 as the result. An invalid namespace behaves as though that namespace argument does not exist. If all specified namespaces are invalid, the function returns 0.

### API

EditCountNeue adds one API list module `list=editcount`.

#### Parameters

* `ecuser` - The users to retrieve number of edits for.
* `ecnamespace` - Only list number of edits in these namespaces.

#### Example

List number of edits of user `Example`:

``` text
api.php?action=query&list=editcount&ecuser=Example
```

Example return:

``` json
{
    "query": {
        "editcount": {
            "user": "Example",
            "userid": 1,
            "stat": [
                {
                    "ns": 0,
                    "count": 100
                },
                {
                    "ns": 2,
                    "count": 50
                }
            ],
            "sum": 150
        }
    }
}
```

## License

The source code of EditCountNeue is licensed under GNU General Public License; either version 2 of the License or any later version is applicable.
