# Avatar upload JSON regression

Run with PHP 8.3:

```sh
php tests/avatar-upload-json/run.php
```

The test constructs the bundled Qiniu SDK configuration under `E_ALL`, then
runs the real avatar endpoint against isolated upload stubs. It verifies that
success, provider failure, malformed provider output, thrown exceptions,
missing files, and CORS preflight all produce unpolluted JSON while retaining
the mini-program's `success`, `data`, and `rawdata` contract.
