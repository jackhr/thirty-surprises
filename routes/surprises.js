var express = require('express');
var router = express.Router();
const surprisesCtrl = require('../controllers/surprises');

router.put('/:id/viewed', surprisesCtrl.viewed)

module.exports = router;