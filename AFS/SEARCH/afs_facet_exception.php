<?php

/** @brief Base class for all exceptions related to facet errors. */
class AfsFacetException extends Exception
{ }

/** @brief Requested facet identifier is unknown. */
class AfsUndefinedFacetException extends AfsFacetException
{ }

/** @brief Configured facet parameter and detected facet parameter are not coherent. */
class AfsInvalidFacetParameterException extends AfsFacetException
{ }


