var express = require('express');
var router = express.Router();
const surprisesCtrl = require('../controllers/surprises');
const ensureLoggedIn = require('../config/ensureLoggedIn');

router.get('/', ensureLoggedIn, surprisesCtrl.all);

router.put('/:id/viewed', surprisesCtrl.viewed);

module.exports = router;