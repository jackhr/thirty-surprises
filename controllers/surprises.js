const Surprise = require('../models/surprise');

module.exports = {
  index,
  show,
  viewed,
};


async function viewed(req, res) {
    try {
        const surprise = await Surprise.findById(req.params.id);

        surprise.viewed = true;

        await surprise.save();
        res.json(surprise);
    } catch(error) {
        res.json({
            error: error.message
        });
    }

}

async function show(req, res) {
    const surprise = await Surprise.findById(req.params.id);
    res.render('surprises/show', {
        surprise,
        title: `surprise: ${surprise.title}`,
    });
}

async function index(req, res) {
    const surprises = await Surprise.find({}).sort('title');
    res.render('surprises/index', { surprises, title: 'All Surprises' });
}