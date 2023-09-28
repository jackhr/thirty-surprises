var express = require('express');
var router = express.Router();
const AdminCtrl = require('../controllers/admin');
const ensureLoggedIn = require('../config/ensureLoggedIn');


router.post('/surprise', ensureLoggedIn, AdminCtrl.create);

router.put('/surprise/:id', ensureLoggedIn, AdminCtrl.update);

router.delete('/surprise/:id', ensureLoggedIn, AdminCtrl.delete);


module.exports = router;