<?php

$(macro :global) { 'GLOBAL_MACRO' } >> { true }

$(macro) { 'LOCAL_MACRO' } >> { true }

return ['GLOBAL_MACRO', 'LOCAL_MACRO'];
