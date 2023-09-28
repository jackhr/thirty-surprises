var express = require('express');
var router = express.Router();
const surprisesCtrl = require('../controllers/surprises');

router.get('/:id', surprisesCtrl.show);

router.get('/', surprisesCtrl.index);

router.put('/:id/viewed', surprisesCtrl.viewed)

module.exports = router;