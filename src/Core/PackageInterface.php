<?php
namespace Seolan\Core;
interface PackageInterface {
  /// Retourne du html inseré avant l'ajout des js
  function getHeader();
  /// Retourne le tableau de fichiers js à inclure
  function getJsIncludes();
  /// Retourne le tableau de fichiers js à inclure
  function getJsAsyncIncludes();
  /// Retourne le tableau de fichiers css à inclure
  function getCssIncludes();
  /// Retourne du html inseré après l'ajout des js
  function getHeader2();
};
?>