var express = require('express');
var router = express.Router();
const IndexCtrl = require('../controllers/index');
const ensureLoggedIn = require('../config/ensureLoggedIn');


router.get('/', IndexCtrl.index);

router.get('/login', (req, res) => res.render('login'));

router.get('/admin', ensureLoggedIn, IndexCtrl.admin);

router.get('/logout', IndexCtrl.logout);

router.post('/login', IndexCtrl.login);


module.exports = router;
