var express = require('express');
var router = express.Router();
const IndexCtrl = require('../controllers/index');


router.get('/', IndexCtrl.index);

router.get('/login', IndexCtrl.showLogin);

router.get('/logout', IndexCtrl.logout);

router.post('/login', IndexCtrl.login);


module.exports = router;
