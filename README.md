# Thrush
![Build status](https://travis-ci.com/Codain/Thrush.svg?branch=master)

**Thrush** is a PHP Library bringing handy features to expand native PHP functions:
- Currently published features:
  - Cache
  - Exception
  - Sitemap (generation only, as per https://www.sitemaps.org/protocol.html, no support for Sitemap Index)
  - Template
  - RC4 encryption
  - API
    - Nominatim ([lookup](https://nominatim.org/release-docs/develop/api/Lookup/) and [reverse](https://nominatim.org/release-docs/develop/api/Reverse/) only)
    - OpenLibrary ([books](https://openlibrary.org/dev/docs/api/books) only)
    - OpenStreetMap
    - Overpass
    - Wikipedia ([TextExtracts](https://en.wikipedia.org/w/api.php?action=help&modules=query%2Bextracts) only)
- Developed but not yet published features:
  - Database (expanding native PDO)
  - Email generation (expanding Template)
  - [Nested set tree](https://en.wikipedia.org/wiki/Nested_set_model)
  - File formats
    - PDF (extract data and resources, merge several PDF files)
  - API
    - Wikidata

Nearly all of those features have been developped (and validated) as part of a web-based collaborative platform development since 2004. They are currently under refactoring in order to be shared as a standalone framework named **Thrush** in the hope it can help other projects. Documentation and unit testings are in progress to assure good quality of this framework.
