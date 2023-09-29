var express = require('express');
var router = express.Router();
const AdminCtrl = require('../controllers/admin');
const SurprisesCtrl = require('../controllers/surprises');
const ensureLoggedIn = require('../config/ensureLoggedIn');


router.get('/surprise/:id/notify', surprisesCtrl.notify)

router.get('/', ensureLoggedIn, AdminCtrl.index);

router.post('/surprise', ensureLoggedIn, SurprisesCtrl.create);

router.put('/surprise/:id', ensureLoggedIn, SurprisesCtrl.update);

router.delete('/surprise/:id', ensureLoggedIn, SurprisesCtrl.delete);


module.exports = router;