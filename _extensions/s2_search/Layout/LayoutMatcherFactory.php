<?php
/**
 * @copyright 2023-2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   s2_search
 */

declare(strict_types=1);

namespace s2_extensions\s2_search\Layout;

use Psr\Log\LoggerInterface;

class LayoutMatcherFactory
{
    /** @noinspection PhpUnnecessarySpreadOperatorForFunctionCallArgumentInspection */
    public static function getFourColumns(LoggerInterface $logger): LayoutMatcher
    {
        $r = new LayoutMatcher($logger);

        // Blocks for more important (relevant) content go first
        $minImgWidth2 = 560;
        $minImgWidth1 = 300;

        // 5 images
        $r->addGroup('i 2i 2i', ...[
            // http://localhost:8081/?/blog/2017/06/12/against_corruption_2
            new BlockGroup(['1/1/3/3'],/* */ (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['', '', '', ''], Block::img1column()),
        ]);
        $r->addGroup('i 2i 2t', ...[
            // http://localhost:8081/?/blog/2019/07/20/metro_navigation
            new BlockGroup(['1/1/3/3'], (new Block())->img(0.06, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3', '2/3'], Block::img1column()),
            new BlockGroup(['', ''],/*  */ Block::thumbnail()),
        ]);
        $r->addGroup('2i i+(1 1) 2i', ...[
            // http://localhost:8081/?/blog/2013/06/12/march
            // http://localhost:8081/?/blog/2011/11/02/24
            // http://localhost:8081/?/blog/2012/06/01/more_physics
            // http://localhost:8081/?/blog/2012/05/06/meeting
            new BlockGroup(['1/2/3/4'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['', '', '2/1/4/2', '2/4/4/5'], Block::img1column()),
            new BlockGroup(['', ''], (new Block())),
        ]);
        $r->addGroup('i 2i+1 i+t+1', ...[
            // http://localhost:8081/?/blog/2011/12/17/Domodedovo2
            // http://localhost:8081/?/blog/2019/01/09/piano
            // http://localhost:8081/?/blog/2018/01/15/airport_piano
            // http://localhost:8081/?/blog/2013/01/21/metro_competition
            // http://localhost:8081/?/blog/2012/06/12/white_ribbon
            // http://localhost:8081/?/blog/2012/05/20/occupy_arbat
            // http://localhost:8081/?/blog/2009/04/26/777
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.7, 0.8, $minImgWidth2)->bigTitle()->text()),
            new BlockGroup(['1/3/2/4', '1/4/2/5', '2/3/3/4'], Block::img1column()->text(0, 0, 200)),
            new BlockGroup(['auto',], Block::thumbnail()->text(0, 0, 200)),
            new BlockGroup(['auto', 'auto'], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i 2i+1 i+ir+1', ...[
            // http://localhost:8081/?/blog/2015/12/31/more_comfortable_seats
            // http://localhost:8081/?/blog/2016/11/27/Acer_Aspire_Switch_10_v
            // http://localhost:8081/?/blog/2010/07/24/Laptop_repair
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.7, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3', '1/4', '2/3'], Block::img1column()->text(0, -100, 400)),
            new BlockGroup([''],/**/ Block::imgRight()->text(0, 150)),
            new BlockGroup(['', ''], (new Block())),
        ]);
        $r->addGroup('i 2i+1 ir+t+1', ...[
            // http://localhost:8081/?/blog/2015/08/26/copy_formatted_excel_table_to_wysiwyg
            // http://localhost:8081/?/blog/2012/04/22/Sutherland
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.7, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3', '2/3'], Block::img1column()->text(0, -100, 400)),
            new BlockGroup(['1/4'],/*   */ Block::imgRight()->text(0, 150)),
            new BlockGroup([''],/*      */ Block::thumbnail()->text(0, 150)),
            new BlockGroup(['', ''], (new Block())->text(0, 0)),
        ]);
        $r->addGroup('i 2i 2t+1', ...[
            // http://localhost:8081/?/blog/2014/09/01/Ursa_Minor
            // http://localhost:8081/?/blog/2012/12/23/copyright_history
            new BlockGroup(['1/1/7/3'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3/4/4', '4/3/7/4'], Block::img1column()),
            new BlockGroup(['1/4/3/5', '3/4/5/5'], Block::thumbnail()),
            new BlockGroup(['auto'], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i i+t+1 2t+1', ...[
            // http://localhost:8081/?/blog/2011/07/18/Better_than_Hubble
            // http://localhost:8081/?/blog/2020/07/05/Yiruma_record
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()->text()),
            new BlockGroup(['1/3'], Block::img1column()->bigTitle(20)),
            new BlockGroup(['auto', 'auto', 'auto'], Block::thumbnail()->text(0, 100)),
            new BlockGroup(['auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i+1 i+1 i+1 t+t', ...[
            // http://localhost:8081/?/blog/2012/05/14/31
            // http://localhost:8081/?/blog/2012/05/09/lettuce
            // http://localhost:8081/?/blog/2008/09/04/Bird_on_Shelf
            // http://localhost:8081/?/blog/2010/05/31/rus
            new BlockGroup(['1/1/3/2', '1/2/3/3', '1/3/3/4'], Block::img1column()->text(0, -100, 400)),
            new BlockGroup(['auto', '2/4/4/5'],/*          */ Block::thumbnail()),
            new BlockGroup(['auto', 'auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i+1 i+1 i+1 ir+t', ...[
            // http://localhost:8081/?/blog/2012/04/12/DirectWrite
            // http://localhost:8081/?/blog/2014/09/21/march_of_peace
            new BlockGroup(['1/1/3/2', '1/2/3/3', '1/3/3/4'], Block::img1column()),
            new BlockGroup([''],/*   */ Block::imgRight()->text(0, 150)),
            new BlockGroup(['2/4/4/5'],/**/ Block::thumbnail()),
            new BlockGroup(['', '', ''], (new Block())),
        ]);
        $r->addGroup('i 2ir+1 2t+1', ...[
            // http://localhost:8081/?/blog/2009/09/13/Life_3_6_beta
            new BlockGroup(['1/1/4/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/4', '2/4'], (new Block())->img(1.0, 4)->imgClass('right')->text(0, 150)),
            new BlockGroup(['1/3', '2/3'], Block::thumbnail()->text(0, 20, 120)),
            new BlockGroup(['', ''], (new Block())),
        ]);

        // 4 images
        $r->addGroup('2i i i+1', ...[
            // http://localhost:8081/?/blog/2010/06/16/Metro_2100
            // http://localhost:8081/?/blog/2020/06/24/noctilucent_clouds_3
            // http://localhost:8081/?/blog/2012/12/15/Lubyanka
            // http://localhost:8081/?/blog/2012/05/09/Chistye_Prudy
            new BlockGroup(['1/2/3/4'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['', '', ''], (new Block())->img(0, 0.8, $minImgWidth1)),
            new BlockGroup([''], (new Block())->text()),
        ]);
        $r->addGroup('i 2i i+2', ...[
            // http://localhost:8081/?/blog/2012/09/30/different_county_2
            // http://localhost:8081/?/blog/2018/06/28/noctilucent_clouds
            // http://localhost:8081/?/blog/2020/06/22/noctilucent_clouds_2
            // http://localhost:8081/?/blog/2021/03/01/algorithmic_complexity
            // http://localhost:8081/?/blog/2009/04/07/Moldova_today
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()->text(0, 0, 500)),
            new BlockGroup(['1/3', '1/4', '2/3/4/4'], Block::img1column()->text(0, -100, 400)),
            new BlockGroup(['', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i 2i+1 i+2', ...[
            // http://localhost:8081/?/blog/2011/11/29/smoking
            // http://localhost:8081/?/blog/2022/05/06/gfhd
            // http://localhost:8081/?/blog/2020/04/26/probability_weight_problem
            // http://localhost:8081/?/blog/2018/01/03/3dplot TODO not so pretty
            // http://localhost:8081/?/blog/2012/12/21/marketing
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()->text(0, 0, 500)),
            new BlockGroup(['1/3/2/4', '1/4/2/5', '2/3/3/4'], Block::img1column()->text(0, -100, 400)),
            new BlockGroup(['auto'],/*    */ (new Block())->text(0, 150)),
            new BlockGroup(['auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('2i i t+1', ...[
            // http://localhost:8081/?/blog/2012/05/30/Do_you_understand_something_in_physics
            new BlockGroup(['1/2/3/4'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/1', '2/1'], (new Block())->img(0.0, 0.8, 300)),
            new BlockGroup(['auto'], Block::thumbnail()->text(0, 150)),
            new BlockGroup(['auto'], (new Block())->text(0, 200)),
        ]);
        $r->addGroup('i 2i t+1', ...[
            // http://localhost:8081/?/blog/2016/12/31/Happy_New_Year
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3', '1/4'], Block::img1column()),
            new BlockGroup(['auto'], Block::thumbnail()->text(0, 150)),
            new BlockGroup(['auto'], (new Block())->text(0, 200)),
        ]);
        $r->addGroup('i i+2 i+t+1', ...[
            // http://localhost:8081/?/blog/2021/04/04/how_to_come_up_with_a_solution_to_olympiad_problem
            // http://localhost:8081/?/blog/2019/09/27/no_smoking_on_balcony
            // http://localhost:8081/?/blog/2018/12/18/new_metro_navigation
            // http://localhost:8081/?/blog/2013/03/23/suffering_of_tall_man
            // http://localhost:8081/?/blog/2008/02/05/Chemical_subst
            // http://localhost:8081/?/blog/2010/04/04/Adobe_Reader
            // http://localhost:8081/?/blog/2010/06/17/Metro_2
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3/2/4', '1/4/2/5'], Block::img1column()),
            new BlockGroup(['2/4/3/5'],/*       */ Block::thumbnail()),
            new BlockGroup(['auto'],/*    */ (new Block())->text(0, 150)),
            new BlockGroup(['auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i i+1 2t', ...[
            // http://localhost:8081/?/blog/2012/06/24/World_War_II
            // http://localhost:8081/?/blog/2012/01/16/hyphens_and_justify
            // http://localhost:8081/?/blog/2012/06/10/Russia_Day
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], Block::img1column()->text(0, 80)),
            new BlockGroup(['1/4/2/5', '2/4/3/5'], Block::thumbnail()),
            new BlockGroup(['auto'], (new Block())->text(0, 250)),
        ]);
        $r->addGroup('i ir+1 2t', ...[
            // http://localhost:8081/?/blog/2017/12/03/100vw
            new BlockGroup(['1/1/4/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], (new Block())->img(1.0, 4)->imgClass('right')->text(0, 150)),
            new BlockGroup(['1/4', '2/4'], Block::thumbnail()->text(0, 150)),
            new BlockGroup(['auto'], (new Block())->text(0, 200)),
        ]);
        $r->addGroup('i i i t', ...[
            //
            new BlockGroup(['1/1', '1/2', '1/3'], Block::img1column()->text(0, 0, 300)),
            new BlockGroup(['auto'],/*         */ Block::thumbnail()->text(0, 80, 120)),
        ]);
        $r->addGroup('i 2i ir+2', ...[
            // http://localhost:8081/?/blog/2007/06/11/TheDailyRoutine
            new BlockGroup(['1/2/4/4'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/1', '2/1/4/2'], Block::img1column()),
            new BlockGroup([''], Block::imgRight()->text(0, 300)),
            new BlockGroup(['', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i+1 i+1 i+1 t+1', ...[
            // http://localhost:8081/?/blog/2019/02/09/pedestrian_etiquette
            // http://localhost:8081/?/blog/2016/12/27/composing_by_start_phrase
            // http://localhost:8081/?/blog/2020/07/26/css_for_print_and_pdf
            // http://localhost:8081/?/blog/2012/03/26/presenter
            // http://localhost:8081/?/blog/2016/12/27/composing_by_start_phrase
            // http://localhost:8081/?/blog/2010/04/21/Future_mice
            new BlockGroup(['1/1', '1/2', '1/3'], Block::img1column()->text(0, 0, 300)),
            new BlockGroup(['auto'],/*         */ Block::thumbnail()->text(0, 80, 120)),
            new BlockGroup(['auto', 'auto', 'auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i+1 i+1 t+1 ir+1', ...[
            // http://localhost:8081/?/blog/2011/11/10/lectures_9x
            // http://localhost:8081/?/blog/2021/10/13/shpilkin
            // http://localhost:8081/?/blog/2020/11/21/arnold_puzzle
            // http://localhost:8081/?/blog/2019/08/22/Severodvinsk_radiation
            // http://localhost:8081/?/blog/2017/11/06/sync_scroll
            // http://localhost:8081/?/blog/2015/08/29/Evernote
            // http://localhost:8081/?/blog/2013/06/05/Cultural_diff
            // http://localhost:8081/?/blog/2012/11/27/math_font
            // http://localhost:8081/?/blog/2008/02/14/Valentines_Day
            // http://localhost:8081/?/blog/2009/11/07/Bug_vkontakte
            // http://localhost:8081/?/blog/2010/09/14/TM
            new BlockGroup(['1/1/2/2', '1/2/2/3'], Block::img1column()->bigTitle(20)->text(0, -100, 500)),
            new BlockGroup(['1/3/2/4'], (new Block())->img(1.0, 10)->imgClass('right')->text(0, 150)),
            new BlockGroup(['auto'],/**/ Block::thumbnail()->text(0, 80, 120)),
            new BlockGroup(['auto', 'auto', 'auto', 'auto'], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i i ir+1 ir+1', ...[
            // http://localhost:8081/?/blog/2012/02/08/different_county
            new BlockGroup(['1/1/3/2', '1/2/3/3'], (new Block())->img(0, 0.8, $minImgWidth1)->bigTitle(30)),
            new BlockGroup(['1/3', '1/4'], (new Block())->img(1.0, 4)->imgClass('right')->text(0, 150)),
            new BlockGroup(['2/3', '2/4'], (new Block())->text(0, 150)),
        ]);
        $r->addGroup('i ir+2 2t+1', ...[
            // http://localhost:8081/?/blog/2009/05/23/Offline_version
            // http://localhost:8081/?/blog/2021/02/26/appearance_of_interface_elements
            // http://localhost:8081/?/blog/2014/12/31/NY
            new BlockGroup(['1/1/4/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], (new Block())->img(1.0, 4)->imgClass('right')->text(0, 150)),
            new BlockGroup(['1/4', '2/4'], Block::thumbnail()->text(0, 20, 120)),
            new BlockGroup(['auto'], (new Block())->text(0, 250)),
            new BlockGroup(['auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i+(1 1) ir+t+1 ir+2', ...[
            // http://localhost:8081/?/blog/2012/04/25/Moscow_climate
            // http://localhost:8081/?/blog/2012/02/25/Savvino-Storozhevsky_Monastery
            // http://localhost:8081/?/blog/2012/12/09/dropbox_and_hibernate
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3', '1/4'], (new Block())->img(1.0, 4)->imgClass('right')->text(0, 150)),
            new BlockGroup(['2/3'], Block::thumbnail()->text(0, 80)),
            new BlockGroup(['2/4'], (new Block())->text(0, 280)),
            new BlockGroup(['', '', '', ''], (new Block())),
        ]);
        $r->addGroup('i+(1 1) i+2 2t+1', ...[
            // http://localhost:8081/?/blog/2007/06/23/Kakiebivautu4eb
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], Block::img1column()->text(0, 80)),
            new BlockGroup(['1/4', '2/4'], Block::thumbnail()->text(0, 150)),
            new BlockGroup(['auto'], (new Block())->text(0, 280)),
            new BlockGroup(['auto', 'auto', 'auto', 'auto'], (new Block())),
        ]);

//        $r->addGroup('i+(1 1) 2i+1 i+2', ...[
//            // http://localhost:8081/?/blog/2017/10/18/vim ? Looks quite ugly
//            new BlockGroup(['1/1/3/3'], (new Block())->img(0.6, 1.0, $minImgWidth2)->bigTitle()),
//            new BlockGroup(['auto', 'auto', 'auto'], Block::img1column()->text(0, 80)),
//            new BlockGroup(['auto'], (new Block())->text(0, 300)),
//            new BlockGroup(['auto', 'auto', 'auto', 'auto'], (new Block())),
//        ]);

        // 3 images
        $r->addGroup('i i+1 i+1', ...[
            // http://localhost:8081/?/blog/2015/02/26/torrent_sequential_downloading
            // http://localhost:8081/?/blog/2022/02/06/how_to_come_up_with_a_solution_to_olympiad_problem_2
            // http://localhost:8081/?/blog/2020/07/07/noctilucent_clouds_4
            // http://localhost:8081/?/blog/2020/04/15/how_to_make_stylus
            // http://localhost:8081/?/blog/2019/09/28/mining
            // http://localhost:8081/?/blog/2014/04/14/march_of_truth
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()->text(0, 0, 800)),
            new BlockGroup(['1/3', '1/4'], (new Block())->img(0, 0.8, $minImgWidth1)->text(0, 50, 500)),
            new BlockGroup(['2/3', '2/4'], (new Block())->text(0, 150)),
        ]);
        $r->addGroup('i i+1 ir+1', ...[
            // http://localhost:8081/?/blog/2018/01/06/URL_for_tex_renderer
            // http://localhost:8081/?/blog/2017/07/03/exponent
            // http://localhost:8081/?/blog/2016/11/12/cashing
            // http://localhost:8081/?/blog/2012/12/12/agreement
            // http://localhost:8081/?/blog/2009/06/11/Russian_flag
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], Block::img1column()),
            new BlockGroup(['1/4'], (new Block())->img(1.0, 10)->imgClass('right')->text(0, 150)),
            new BlockGroup(['2/3', '2/4'], (new Block())->text(0, 200)),
        ]);
        $r->addGroup('i (i i)+1+(1 1)', ...[
            // http://localhost:8081/?/blog/2021/01/08/emias
            // http://localhost:8081/?/blog/2018/01/20/maxwells_equation
            // http://localhost:8081/?/blog/2016/07/11/fms
            // http://localhost:8081/?/blog/2014/04/22/cars_on_sidewalk
            // http://localhost:8081/?/blog/2012/07/01/punctuation_marks
            // http://localhost:8081/?/blog/2009/04/10/Attendance
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.5, 0.8, $minImgWidth2)->bigTitle()->text(0, -100, 1500)),
            new BlockGroup(['2/3', '2/4'], Block::img1column()->text(0, -100, 500)),
            new BlockGroup(['1/3/2/5'], (new Block())->text(0, 300)->bigTitle(500)),
            new BlockGroup(['', ''], (new Block())->text(0, 0)),
        ]);
        $r->addGroup('i i+2 i+2', ...[
            // http://localhost:8081/?/blog/2020/10/28/format_concept
            // http://localhost:8081/?/blog/2013/01/21/new_lenta
            // http://localhost:8081/?/blog/2013/02/07/turnstile
            // http://localhost:8081/?/blog/2016/09/20/elections
            // http://localhost:8081/?/blog/2015/10/10/war
            // http://localhost:8081/?/blog/2021/03/16/data_analysis
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.5, 0.8, $minImgWidth2)->bigTitle()->text(0, -100, 1500)),
            new BlockGroup(['1/3', '1/4'], Block::img1column()->text(0, -100, 500)),
            new BlockGroup(['', '', '', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i ir+1 i', ...[
            // http://localhost:8081/?/blog/2010/03/04/Transport
            new BlockGroup(['1/1/3/3'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/4/3/5'], Block::img1columnTall()->bigTitle(20)),
            new BlockGroup([''], (new Block())->img(1.0, 4)->imgClass('right')->text(0, 150)),
            new BlockGroup([''], (new Block())->text(0, 150)),
        ]);
        $r->addGroup('i i+2 ir+2', ...[
            // http://localhost:8081/?/blog/2011/05/28/Discrimination
            // http://localhost:8081/?/blog/2011/11/13/free_will
            // http://localhost:8081/?/blog/2020/07/12/move_php_sessions_between_servers
            // http://localhost:8081/?/blog/2010/01/02/Dimensions
            new BlockGroup(['1/1/4/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()->text()),
            new BlockGroup(['1/3'], Block::img1column()),
            new BlockGroup(['1/4'], (new Block())->img(1.0, 10)->imgClass('right')->text(0, 150)),
            new BlockGroup(['auto', 'auto', 'auto', 'auto'], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i i+1 ir+1 (2)', ...[
            // http://localhost:8081/?/blog/2012/12/09/dropbox_and_hibernate
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], Block::img1columnTall()->bigTitle(20)),
            new BlockGroup(['1/4'], (new Block())->img(1.0, 4)->imgClass('right')->text(0, 150)),
            new BlockGroup(['auto', 'auto'], (new Block())->text(0, 150)),
        ]);
        $r->addGroup('i i i', ...[
            new BlockGroup(['1/1/2/2'], (new Block())->img(1, 1.6, $minImgWidth1)->text(0, 150)),
            new BlockGroup(['1/2/2/4'], (new Block())->img(0.5, 0.7, $minImgWidth2)->text()),
            new BlockGroup(['1/4'], (new Block())->img(1, 1.6, $minImgWidth1)),
        ]);
        $r->addGroup('i i+2 2t+1', ...[
            // http://localhost:8081/?/blog/2006/06/26/Nepereno6udilet
            new BlockGroup(['1/1/4/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], (new Block())->img(0, 0.8, $minImgWidth1)->bigTitle(30)),
            new BlockGroup(['1/4', '2/4'], Block::thumbnail()->text(0, 50)),
            new BlockGroup(['2/3', '', ''], (new Block())->text(0, 150)),
        ]);
        $r->addGroup('i i+1 t+1', ...[
            // http://localhost:8081/?/blog/2017/12/07/fonts
            // http://localhost:8081/?/blog/2013/05/25/modern_drafts
            // http://localhost:8081/?/blog/2009/11/10/PDF
            // http://localhost:8081/?/blog/2009/11/25/Left_outer_join
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], Block::img1column()->bigTitle(20)),
            new BlockGroup(['1/4'], Block::thumbnail()->text(0, 100)),
            new BlockGroup(['auto', 'auto'], (new Block())->text(1, 150)),
        ]);
        $r->addGroup('i i+2 t+2', ...[
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], Block::img1column()->bigTitle(20)),
            new BlockGroup(['1/4'], Block::thumbnail()->text(0, 100)),
            new BlockGroup(['auto', 'auto'], (new Block())->text(1, 150)),
            new BlockGroup(['auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i t+1 ir+1', ...[
            // http://localhost:8081/?/blog/2008/01/30/Tunnel_problem
            new BlockGroup(['1/2/4/4'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/1'], (new Block())->img(1.0, 4)->imgClass('right')->text(0, 150)),
            new BlockGroup(['2/1'], Block::thumbnail()->bigTitle(20)->text(0, 150)),
            new BlockGroup(['', ''], (new Block())->text(0, 200)),
        ]);
        $r->addGroup('i ir+2 t+2', ...[
            // http://localhost:8081/?/blog/2020/10/18/negative_use_of_cleaner
            // http://localhost:8081/?/blog/2017/09/13/zaryadye
            // http://localhost:8081/?/blog/2007/10/17/Perevod4egjjot
            // http://localhost:8081/?/blog/2009/03/28/Delphi_2009
            // http://localhost:8081/?/blog/2009/11/20/Lap_top
            // http://localhost:8081/?/blog/2010/04/10/UTF8_bad_chars
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.5, 0.8, $minImgWidth2)->bigTitle()->text(0, -100, 800)),
            new BlockGroup(['1/3'], (new Block())->img(1.0, 4)->imgClass('right')->text(0, 150)),
            new BlockGroup(['1/4'], Block::thumbnail()->text()),
            new BlockGroup(['auto', 'auto'], (new Block())->text(0, 200)),
            new BlockGroup(['auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i t+2 t+2', ...[
            // http://localhost:8081/?/blog/2007/04/19/Jiznjvnutripuzi
            new BlockGroup(['1/1/4/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3', '1/4'], (new Block())->img(0, 1.25, 90, 300)->imgClass('thumb')->bigTitle(20)->text()),
            new BlockGroup(['', ''], (new Block())->text(0, 200)),
            new BlockGroup(['', ''], (new Block())),
        ]);
        $r->addGroup('i ir 2 t', ...[
            // http://localhost:8081/?/blog/2011/06/10/Opera_car
            // http://localhost:8081/?/blog/2011/03/16/s2_and_susy
            new BlockGroup(['1/1/3/2'], Block::img1column()->bigTitle()),
            new BlockGroup(['1/2/3/3'], Block::imgRight()->text(0, 150)),
            new BlockGroup(['1/4/3/5'], Block::thumbnail()->text()),
            new BlockGroup(['', ''], (new Block())->text(0, 200)),
        ]);
        $r->addGroup('i i+1 t+3', ...[
            // http://localhost:8081/?/blog/2006/09/16/Odostovernosti
            new BlockGroup(['1/1/5/3'], (new Block())->img(0.5, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3/4/4'], Block::img1columnTall()->bigTitle(20)),
            new BlockGroup(['1/4'], Block::thumbnail()->text(0, 00)),
            new BlockGroup(['', '', '', ''], (new Block())),
        ]);
        $r->addGroup('i_wider i+2 t+2', ...[
            // http://localhost:8081/?/blog/2012/01/21/churoffmetics
            // http://localhost:8081/?/blog/2022/02/03/bank_is_not_on_shell
            // http://localhost:8081/?/blog/2012/05/26/talk_on_cosmology
            new BlockGroup(['1/1/4/3'], (new Block())->img(0, 0.6, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], Block::img1column()->bigTitle(20)),
            new BlockGroup(['1/4'], Block::thumbnail()->text(0, 100)),
            new BlockGroup(['auto', 'auto', 'auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i i ir+1 2', ...[
            // http://localhost:8081/?/blog/2011/10/03/Programming_in_USSR
            // http://localhost:8081/?/blog/2009/12/11/LHC_diploma
            new BlockGroup(['1/1/3/2', '1/2/3/3'], (new Block())->img(0, 0.8, $minImgWidth1)->bigTitle(30)->text(0, 0, 500)),
            new BlockGroup(['1/3',], (new Block())->img(1.0, 4)->imgClass('right')->text(0, 150)),
            new BlockGroup(['1/4'], (new Block())->text(0, 200)),
            new BlockGroup(['2/3', '2/4'], (new Block())),
        ]);
        $r->addGroup('i+1 i+1 i+1 2', ...[
            // http://localhost:8081/?/blog/2017/10/18/vim
            // http://localhost:8081/?/blog/2020/04/12/disinfection
            // http://localhost:8081/?/blog/2006/12/10/KursovaapoSocia
            // http://localhost:8081/?/blog/2021/08/29/airport_piano2
            new BlockGroup(['1/1', '1/2', '1/3'], Block::img1column()->text(0, 0, 300)),
            new BlockGroup(['1/4'], (new Block())->text(0, 300)),
            new BlockGroup(['auto', 'auto', 'auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i+(1 1) ir+1 ir+1', ...[
            // http://localhost:8081/?/blog/2007/02/08/Fantaziabezlogi
            // http://localhost:8081/blog/2008/02/08/Vacation_end
            new BlockGroup(['1/1/4/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3/3/4', '1/4/3/5'], (new Block())->img(1.0, 4)->imgClass('right')->text(0, 150)),
            new BlockGroup(['3/3/5/4', '3/4/5/5'], (new Block())->text(0, 280)),
            new BlockGroup(['4/1/5/2', '4/2/5/3'], (new Block())),
        ]);
        $r->addGroup('i+(1 1) ir+2 ir+2', ...[
            // http://localhost:8081/?/blog/2017/10/21/binbonus
            // http://localhost:8081/?/blog/2020/04/05/do_not_touch_face
            // http://localhost:8081/?/blog/2009/05/12/One_day
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3', '1/4'], (new Block())->img(1.0, 4)->imgClass('right')->text(0, 150)),
            new BlockGroup(['auto', 'auto'], (new Block())->text(0, 280)),
            new BlockGroup(['auto', 'auto', 'auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i+1 t+1 i+1 2', ...[
            // http://localhost:8081/?/blog/2010/10/25/Help_the_blind
            // http://localhost:8081/?/blog/2014/01/22/latex_for_web
            new BlockGroup(['1/1', '1/3'], Block::img1column()->bigTitle(40)->text(0, 0, 300)),
            new BlockGroup(['1/2'], Block::thumbnail()->text(0, 80, 300)),
            new BlockGroup(['1/4'], (new Block())->text(0, 300)),
            new BlockGroup(['', '', '', ''], (new Block())),
        ]);
        $r->addGroup('i t+3 ir+2', ...[
            // http://localhost:8081/?/blog/2011/12/22/PHP_memory
            // http://localhost:8081/?/blog/2007/06/10/RedesignWplanet
            // http://localhost:8081/?/blog/2010/01/26/Happy_NY
            // http://localhost:8081/?/blog/2017/12/29/mobile_network_vulnerability
            new BlockGroup(['1/1/5/3'], (new Block())->img(0.5, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup([''],/*   */ Block::thumbnail()->text(0, 150)->bigTitle(15)),
            new BlockGroup(['1/4/3/5'], Block::imgRight()->text(0, 150)->bigTitle(15)),
            new BlockGroup(['', '', '', '', ''], (new Block())),
        ]);
        $r->addGroup('i+(1 1) i+2 ir+2', ...[
            // http://localhost:8081/?/blog/2009/11/02/CSS_vars
            // http://localhost:8081/?/blog/2007/12/06/LJandSup
            // http://localhost:8081/?/blog/2010/04/02/Rocket_science
            // http://localhost:8081/?/blog/2006/06/13/Voprosnazasipku
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup([''], Block::img1column()->text(0, 0, 500)),
            new BlockGroup([''], Block::imgRight()->text(0, 200)),
            new BlockGroup(['', ''], (new Block())->text(0, 150)),
            new BlockGroup(['', '', '', ''], (new Block())),
        ]);
        $r->addGroup('i+(1 1) t+2 ir+2', ...[
            // http://localhost:8081/?/blog/2006/08/08/Zabavniigul
            new BlockGroup(['1/1/3/3'], (new Block())->img(0.5, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup([''], Block::thumbnail()->text(0, 150)),
            new BlockGroup([''], Block::imgRight()->text()),
            new BlockGroup(['', ''], (new Block())->text(0, 150)),
            new BlockGroup(['', '', '', ''], (new Block())),
        ]);
        $r->addGroup('2 t+1 t+1 ir', ...[
            // http://localhost:8081/?/blog/2007/01/01/DizainervstileV
            new BlockGroup([''], (new Block())->bigTitle()->text()),
            new BlockGroup(['', ''], Block::thumbnail()->text(0, 150)),
            new BlockGroup(['1/4/3/5'], Block::imgRight()->text()),
            new BlockGroup(['', '', ''], (new Block())),
        ]);

        // 2 images
        $r->addGroup('i_wider i 1', ...[
            // http://localhost:8081/?/blog/2011/05/17/Lock_free_editing
            // http://localhost:8081/?/blog/2011/01/07/Laptop_repair
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.5, $minImgWidth2)->bigTitle()->text()),
            new BlockGroup(['1/3'], (new Block())->img(0, 0.8, $minImgWidth1)->bigTitle(20)),
            new BlockGroup(['1/4'], (new Block())->text(150, 300)->bigTitle(20)),
        ]);
        $r->addGroup('i_wider i+1 2', ...[
            // http://localhost:8081/?/blog/2017/06/25/mipt_vs_msu
            // http://localhost:8081/?/blog/2005/11/08/Mozgvsvakuum
            // http://localhost:8081/?/blog/2006/05/31/Naukoobrazie
            // http://localhost:8081/?/blog/2009/08/07/Tabula_rasa
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.5, $minImgWidth2)->bigTitle()->text()),
            new BlockGroup(['1/3'], (new Block())->img(0, 0.8, $minImgWidth1)->bigTitle(20)),
            new BlockGroup(['1/4'], (new Block())->text(150, 300)->bigTitle(20)),
            new BlockGroup(['2/3', '2/4'], (new Block())),
        ]);
        $r->addGroup('i 2 i+1', ...[
            // http://localhost:8081/?/blog/2021/07/20/noctilucent_clouds_5
            // http://localhost:8081/?/blog/2012/01/16/photons_gravity
            // http://localhost:8081/?/blog/2011/07/09/5_years_back
            // http://localhost:8081/?/blog/2018/03/25/software_engineer_responsibility
            // http://localhost:8081/?/blog/2015/05/03/photon_point_of_view
            // http://localhost:8081/?/blog/2006/01/18/Izmeneniaklimat
            // http://localhost:8081/?/blog/2009/10/03/Opensource
            // http://localhost:8081/?/blog/2010/12/19/Relativity
            new BlockGroup(['1/1/3/3'], (new Block())->img(0.5, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/4'], (new Block())->img(0.5, 0.8, $minImgWidth1)),
            new BlockGroup(['1/3'], (new Block())->text(250, 400)->bigTitle(20)),
            new BlockGroup(['2/3', '2/4'], (new Block())->text(0, 200)),
        ]);
        $r->addGroup('i i+1 2', ...[
            // http://localhost:8081/?/blog/2012/02/21/lynch
            // http://localhost:8081/?/blog/2022/02/27/proxy_via_ssh
            // http://localhost:8081/?/blog/2021/08/29/airport_piano2
            // http://localhost:8081/?/blog/2020/08/30/insert_ignore
            // http://localhost:8081/?/blog/2020/02/09/front_and_back_repos
            // http://localhost:8081/?/blog/2016/01/24/LHC_epistemology
            // http://localhost:8081/?/blog/2006/04/05/Desatjistoriiom
            // http://localhost:8081/?/blog/2009/11/07/Zadornov
            new BlockGroup(['1/1/3/3'], (new Block())->img(0.5, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], (new Block())->img(0, 1, $minImgWidth1)->bigTitle(20)->text(0, -200, 500)),
            new BlockGroup(['1/4'], (new Block())->text(150, 300)->bigTitle(20)),
            new BlockGroup(['2/3', '2/4'], (new Block())->text(0, 200)),
        ]);
        $r->addGroup('i i_wider+1 2', ...[
            // http://localhost:8081/?/blog/2017/08/09/software_engineering_and_computer_science
            new BlockGroup(['1/1/3/3'], (new Block())->img(0.5, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], (new Block())->img(0, 0.5, $minImgWidth1)->bigTitle(20)->text(0, 280)),
            new BlockGroup(['1/4'], (new Block())->text(150, 300)->bigTitle(20)),
            new BlockGroup(['2/3', '2/4'], (new Block())->text(0, 200)),
        ]);
        $r->addGroup('i + 3 + ir', ...[
            // http://localhost:8081/?/blog/2012/06/24/Chistye_Prudy_OK
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/4'],/**/ (new Block())->img(1.5, 10)->imgClass('right')->text(0, 150)),
            new BlockGroup(['1/3'],/*   */ (new Block())->text(150, 300)),
            new BlockGroup(['2/3', '2/4'], (new Block())->text(0, 200)),
        ]);
        $r->addGroup('i i+1 3', ...[
            // http://localhost:8081/?/blog/2009/01/28/GSI
            // http://localhost:8081/?/blog/2020/09/13/mathcha
            // http://localhost:8081/?/blog/2011/10/17/qft
            // http://localhost:8081/?/blog/2019/08/06/no_business_logic_in_database
            // http://localhost:8081/?/blog/2017/11/19/iceberg
            // http://localhost:8081/?/blog/2007/02/03/Naborizre4eniid
            // http://localhost:8081/?/blog/2008/08/26/LHC
            // http://localhost:8081/?/blog/2020/05/20/Kazakov_lecture
            // http://localhost:8081/?/blog/2012/01/28/kollaideru_net
            // http://localhost:8081/?/blog/2011/06/15/cool_S2
            new BlockGroup(['1/1/4/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()->text(0, 0, 500)),
            new BlockGroup(['1/3/3/4'], Block::img1column()->text(0, 150)->bigTitle(15)),
            new BlockGroup(['', '', '', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i 1+i 4', ...[
            // http://localhost:8081/?/blog/2011/12/16/Putins_elections
            // http://localhost:8081/?/blog/2011/12/25/Psychologists
            // http://localhost:8081/?/blog/2006/09/21/Teorfizvsob6efi
            new BlockGroup(['1/1/5/3'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()->text(0, 0, 500)),
            new BlockGroup(['2/3/5/4'],/*     */ Block::img1column()->text(0, 300)->bigTitle(15)),
            new BlockGroup(['', '', '', '', ''], (new Block())->text(0, 100)),
        ]);
        $r->addGroup('i_wider i+1 4', ...[
            // http://localhost:8081/?/blog/2021/02/24/DO_and_services_in_OOP
            new BlockGroup(['1/1/5/3'], (new Block())->img(0, 0.6, $minImgWidth2)->bigTitle()->text(0, 0, 500)),
            new BlockGroup(['1/3/4/4'], Block::img1column()->text(0, 300)->bigTitle(15)),
            new BlockGroup(['1/4'],/*     */ (new Block())->text(0, 300)),
            new BlockGroup(['', '', '', ''], (new Block())),
        ]);
        $r->addGroup('i 2 ir+1', ...[
            // http://localhost:8081/?/blog/2010/09/24/Moscow
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/4'],/**/ Block::imgRight()->text(0, 80)),
            new BlockGroup(['1/3'], (new Block())->text(150, 300)),
            new BlockGroup(['', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i 3 ir+2', ...[
            // http://localhost:8081/?/blog/2021/08/30/meduza_logo
            // http://localhost:8081/?/blog/2018/09/15/five_minutes_of_paranoia
            new BlockGroup(['1/1/4/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/4'],/**/ Block::imgRight()->text(0, 80)),
            new BlockGroup(['1/3'],/*     */ (new Block())->text(150, 300)),
            new BlockGroup(['', '', '', ''], (new Block())->text(0, 80)),
        ]);
        // /blog/2013/02/24/language
        $r->addGroup('i i', ...[
            new BlockGroup(['1/1/3/3', '1/3/3/5'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
        ]);
        $r->addGroup('i i 2 2', ...[
            // http://localhost:8081/?/blog/2011/02/22/Perpetuum_mobile
            // http://localhost:8081/?/blog/2007/12/02/Quotation_marks
            new BlockGroup(['1/1/3/2', '1/2/3/3'], Block::img1column()->bigTitle(30)->text(0, 0, 500)),
            new BlockGroup(['1/3', '1/4'], (new Block())->text(0, 200)),
            new BlockGroup(['2/3', '2/4'], (new Block())),
        ]);
        $r->addGroup('(i i)+(2 2 2)', ...[
            // http://localhost:8081/?/blog/2017/08/16/composing_by_poems
            // http://localhost:8081/?/blog/2018/03/18/elections
            // http://localhost:8081/?/blog/2011/10/20/Tandem
            // http://localhost:8081/?/blog/2011/03/27/Leyden_jar
            // http://localhost:8081/?/blog/2007/01/03/RaspredelenieZi
            // http://localhost:8081/?/blog/2011/12/30/Prohorov
            // http://localhost:8081/?/blog/2019/09/01/protests_and_elections
            // http://localhost:8081/?/blog/2015/05/01/debian_8
            new BlockGroup(['1/1/2/4', '1/4/2/7'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['2/1/3/3', '2/3/3/5', '2/5/3/7', '3/1/4/3', '3/3/4/5', '3/5/4/7'], (new Block())),
        ]);
        $r->addGroup('i+(1 1) 1+(i 1)+(1 1)', ...[
            // http://localhost:8081/?/blog/2009/03/05/Web_2_0
            // http://localhost:8081/?/blog/2011/04/12/squared_friction_oscillations
            // http://localhost:8081/?/blog/2019/01/05/introduction_to_scrum
            // http://localhost:8081/?/blog/2008/02/29/February_29
            // http://localhost:8081/?/blog/2008/03/01/Zakolebal
            // http://localhost:8081/?/blog/2009/09/29/IE_6_death
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['2/3'], Block::img1column()->text(0, -100, 500)),
            new BlockGroup(['1/3/2/5'], (new Block())->text(0, 400)->bigTitle()),
            new BlockGroup([''], (new Block())->text(0, 300)),
            new BlockGroup(['', '', '', ''], (new Block())),
        ]);
        $r->addGroup('i+(1 1) i+2 3', ...[
            // http://localhost:8081/?/blog/2020/04/07/navalny
            // http://localhost:8081/?/blog/2011/03/12/anecdote
            // http://localhost:8081/?/blog/2020/09/14/interview_tests
            // http://localhost:8081/?/blog/2017/05/08/Writing_OOP_in_PhpStorm
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'], Block::img1column()->text(0, 80)),
            new BlockGroup(['auto', 'auto', 'auto'], (new Block())->text(0, 280)),
            new BlockGroup(['auto', 'auto', 'auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i i 1+(2 2)', ...[ // TODO maybe '1+(2 2) i i' is better&
            // http://localhost:8081/?/blog/2009/11/13/Firebug
            new BlockGroup(['1/1/4/2', '1/2/4/3'], Block::img1column()->text(0, 0, 500)), // 4!
            new BlockGroup(['1/3/2/5'], (new Block())->bigTitle()->text(150, 300)),
            new BlockGroup(['2/3', '2/4'], (new Block())->text(0, 300)),
            new BlockGroup(['', ''], (new Block())),
        ]);
        $r->addGroup('i 3 i 3', ...[
            // http://localhost:8081/?/blog/2012/07/05/Gmail_buttons
            new BlockGroup(['1/1', '1/3'], Block::img1column()->bigTitle(40)->text(0, 0, 500)), // 4!
            new BlockGroup(['1/2', '1/4'], (new Block())->text(0, 300)),
            new BlockGroup(['auto', 'auto', 'auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i i 1 1', ...[
            new BlockGroup(['1/1', '1/2'], (new Block())->img(0, 0.8, $minImgWidth1)->bigTitle(30)),
            new BlockGroup(['auto', 'auto'], (new Block())->text(0, 300)),
        ]);
        $r->addGroup('i t+1 2', ...[
            // http://localhost:8081/?/blog/2017/04/16/PhpStorm_myths_busted
            // http://localhost:8081/?/blog/2006/10/30/QIP
            // http://localhost:8081/?/blog/2007/05/29/MicrosoftReport
            // http://localhost:8081/?/blog/2007/11/04/Vpe4atleniaot4t
            // http://localhost:8081/?/blog/2009/01/22/CIS
            // http://localhost:8081/?/blog/2010/03/31/VSC
            // http://localhost:8081/?/blog/2011/02/04/S2_alpha_1
            new BlockGroup(['1/1/4/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()->text(0, -300, 1500)),
            new BlockGroup(['1/3'],/**/ Block::thumbnail()->text(0, -100, 300)->bigTitle()),
            new BlockGroup(['1/4', '2/3', '2/4'], (new Block())->text(0, 300)),
        ]);
        $r->addGroup('i t+2 3', ...[
            // http://localhost:8081/?/blog/2011/07/26/Ya_gradient
            // http://localhost:8081/?/blog/2014/03/16/navalny_blocked
            // http://localhost:8081/?/blog/2014/01/29/circle_tractrix
            // http://localhost:8081/?/blog/2012/10/16/Children_in_history
            new BlockGroup(['1/1/4/3'], (new Block())->img(0.5, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3'],/**/ Block::thumbnail()->text(0, 80)),
            new BlockGroup(['1/4'],/*                 */ (new Block())->text(80, 120)),
            new BlockGroup(['2/3', '2/4', '3/3', '3/4'], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i 2 2 ir', ...[
            // http://localhost:8081/?/blog/2011/08/24/business_lynch
            // http://localhost:8081/?/blog/2007/12/09/SiteX
            // http://localhost:8081/?/blog/2009/11/12/Collider
            new BlockGroup(['1/1/3/2'], Block::img1column()->bigTitle()->text(0, 0, 500)),
            new BlockGroup(['1/4/3/5'], Block::imgRight()->text(0, 350)),
            new BlockGroup(['', ''], (new Block())->text(0, 200)),
            new BlockGroup(['', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i 2 2 ir (2)', ...[
            // http://localhost:8081/?/blog/2006/10/07/Matemati4eskaas
            new BlockGroup(['1/1/3/2'], Block::img1column()->bigTitle()->text(0, 0, 500)),
            new BlockGroup(['1/4/3/5'], Block::imgRight2()->text(0, 350)),
            new BlockGroup(['', ''], (new Block())->text(0, 200)),
            new BlockGroup(['', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i ir 2 3', ...[
            // http://localhost:8081/?/blog/2011/08/24/business_lynch
            // http://localhost:8081/?/blog/2011/10/09/10_rubles
            // http://localhost:8081/?/blog/2020/05/11/megafon_voice_mail
            // http://localhost:8081/?/blog/2018/09/15/five_minutes_of_paranoia
            // http://localhost:8081/?/blog/2007/10/03/Eje
            new BlockGroup(['1/1/7/2'], Block::img1column()->bigTitle()->text(0, 0, 500)),
            new BlockGroup(['1/2/7/3'], Block::imgRight()->text(0, 350)),
            new BlockGroup(['1/3/4/4', '4/3/7/4'],/*       */ (new Block())->text(0, 200)),
            new BlockGroup(['1/4/3/5', '3/4/5/5', '5/4/7/5'], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i 2 2 t', ...[
            // http://localhost:8081/?/blog/2007/12/07/OpenID
            // http://localhost:8081/?/blog/2009/09/29/Occupation
            new BlockGroup(['1/1/3/2'], Block::img1column()->bigTitle()->text(0, 0, 500)),
            new BlockGroup(['1/4/3/5'], Block::thumbnail()->text(0, 350)),
            new BlockGroup(['', ''], (new Block())->text(0, 200)),
            new BlockGroup(['', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('t 2 2 ir', ...[
            // http://localhost:8081/?/blog/2006/12/07/Grammatikaiasjk
            // http://localhost:8081/?/blog/2009/12/12/Thumbelina
            // http://localhost:8081/?/blog/2010/01/04/s2_db
            new BlockGroup(['1/1/3/2'], Block::thumbnail()->bigTitle()->text(0, 0, 500)),
            new BlockGroup(['1/4/3/5'], Block::imgRight()->text(0, 350)),
            new BlockGroup(['', ''], (new Block())->text(0, 200)),
            new BlockGroup(['', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('t ir 2 3', ...[ // TODO maybe flip to ir t 2 3
            // http://localhost:8081/?/blog/2010/05/07/WirelessHeadset
            new BlockGroup(['1/1/7/2'], Block::thumbnail()->text(0, 250)),
            new BlockGroup(['1/2/7/3'], (new Block())->img(1.5, 10)->imgClass('right')->text(0, 250)),
            new BlockGroup(['1/3/4/4', '4/3/7/4'],/*       */ (new Block())->text(0, 300)),
            new BlockGroup(['1/4/3/5', '3/4/5/5', '5/4/7/5'], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('t ir 2', ...[
            // http://localhost:8081/?/blog/2006/12/20/Onaukah
            new BlockGroup(['1/2/3/4'], (new Block())->img(0.4, 2, 180)->imgClass('right')->bigTitle()->text()),
            new BlockGroup(['1/1/3/2'], (new Block())->img(0, 1.2, 90, 300)->imgClass('thumb')->bigTitle(20)->text()),
            new BlockGroup(['', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i+(1 1) 3 ir+2', ...[
            // http://localhost:8081/?/blog/2017/06/25/dealing_with_legacy
            // http://localhost:8081/?/blog/2009/05/27/Clinic
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/4'], Block::imgRight()->text()),
            new BlockGroup(['auto', 'auto', 'auto'], (new Block())->text(0, 280)),
            new BlockGroup(['auto', 'auto', 'auto', 'auto'], (new Block())),
        ]);
        $r->addGroup('i 1+(2 2) t', ...[
            // http://localhost:8081/?/blog/2010/10/03/GR
            new BlockGroup(['1/1/4/2'], (new Block())->img(0, 1.5, $minImgWidth1)->text(0, 0, 250)),
            new BlockGroup(['1/4/4/5'], Block::thumbnail()->text()),
            new BlockGroup(['1/2/2/4'], (new Block())->text(80, 300)->bigTitle()),
            new BlockGroup(['', '', '', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('t 1+(2 2) t', ...[
            // http://localhost:8081/?/blog/2008/06/08/rulinki
            // http://localhost:8081/?/blog/2012/02/23/obscure_sign
            // http://localhost:8081/?/blog/2008/07/03/PHP_mkdir
            // http://localhost:8081/?/blog/2009/12/20/VKontakte
            // http://localhost:8081/?/blog/2010/12/17/browserjs
            new BlockGroup(['1/1/4/2', '1/4/4/5'], Block::thumbnail()->text(0, 250)),
            new BlockGroup(['1/2/2/4'], (new Block())->text(80, 300)->bigTitle()),
            new BlockGroup(['', '', '', ''], (new Block())->text(0, 80)),
        ]);

        // 1 image
        $r->addGroup('i 2 2', ...[
            // http://localhost:8081/?/blog/2018/05/29/refactor_to_fix_bugs
            // http://localhost:8081/?/blog/2011/11/03/science_sect
            // http://localhost:8081/?/blog/2012/05/01/br_bq
            // http://localhost:8081/?/blog/2012/06/04/premiers_authority
            // http://localhost:8081/?/blog/2013/06/01/no_smoking
            // http://localhost:8081/?/blog/2021/08/27/fake_pop3_server
            // http://localhost:8081/?/blog/2013/11/04/ember_and_debug
            // http://localhost:8081/?/blog/2012/10/25/Halloween
            // http://localhost:8081/?/blog/2020/08/28/nginx_cache
            new BlockGroup(['1/1/3/3'], (new Block())->img(0.05, 1, $minImgWidth2)->bigTitle()),
            new BlockGroup(['', '', '', ''], (new Block())->text(0, 300)),
        ]);
        $r->addGroup('i 3 3', ...[
            // http://localhost:8081/?/blog/2020/06/27/php_fpm_queue
            // http://localhost:8081/?/blog/2020/09/19/take_it_literally
            // http://localhost:8081/?/blog/2018/01/22/regular_collection
            new BlockGroup(['1/1/4/3'], (new Block())->img(0, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['1/3', '1/4'],/*          */ (new Block())->text(150, 300)->bigTitle(25)),
            new BlockGroup(['2/3', '2/4', '3/3', '3/4'], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('ir 1 1', ...[
            // http://localhost:8081/?/blog/2012/04/27/Links
            // http://localhost:8081/?/blog/2012/04/26/Opera_12
            // http://localhost:8081/?/blog/2012/09/25/Modem_speed
            // http://localhost:8081/?/blog/2013/11/16/ignorance
            // http://localhost:8081/?/blog/2011/08/03/Backups
            // http://localhost:8081/?/blog/2020/03/29/sars-cov2
            // http://localhost:8081/?/blog/2020/10/25/talk_rule
            // http://localhost:8081/?/blog/2016/09/20/adblock_myths
            // http://localhost:8081/?/blog/2012/06/27/S2_movie
            // http://localhost:8081/?/blog/2012/03/05/elections
            // http://localhost:8081/?/blog/2009/11/11/Samierazdrajau6
            new BlockGroup(['1/1/3/3'], (new Block())->img(0.4, 2, 180)->imgClass('right')->bigTitle()->text()),
            new BlockGroup(['1/3', '1/4'], (new Block())->text(0, 300)),
        ]);
        $r->addGroup('ir 1 1 (2)', ...[
            // http://localhost:8081/?/blog/2006/02/23/Galo6iza50
            // http://localhost:8081/?/blog/2006/01/01/Novogodneepozdr
            new BlockGroup(['1/1/3/3'], Block::imgRight2()->bigTitle()->text()),
            new BlockGroup(['1/3', '1/4'], (new Block())->text(0, 300)),
        ]);
        $r->addGroup('i 2 2 2', ...[
            // http://localhost:8081/?/blog/2011/05/14/Constitutional_court
            // http://localhost:8081/?/blog/2012/01/15/No_comments
            // http://localhost:8081/?/blog/2011/07/20/written_ru_6
            // http://localhost:8081/?/blog/2010/08/01/Bad_headphones
            // http://localhost:8081/?/blog/2020/01/19/perzoj
            // http://localhost:8081/?/blog/2015/06/12/science_legacy
            // http://localhost:8081/?/blog/2012/07/30/Opera_crash
            // http://localhost:8081/?/blog/2012/04/21/Faith_in_justice
            new BlockGroup(['1/1/3/2'], (new Block())->img(0, 0.9, $minImgWidth1)->bigTitle()->text(0, 0, 200)),
            new BlockGroup(['1/2', '1/3', '1/4'], (new Block())->text(0, 280)),
            new BlockGroup(['2/2', '2/3', '2/4'], (new Block())),
        ]);
        $r->addGroup('i_wider 2 2 1', ...[
            // ?
            new BlockGroup(['1/1/3/2'], (new Block())->img(0, 0.5, $minImgWidth1)->bigTitle()),
            new BlockGroup(['1/2/3/3'], (new Block())->text(0, 280)),
            new BlockGroup(['', '', '', ''], (new Block())),
        ]);
        $r->addGroup('i 2 2 1', ...[
            // ?
            new BlockGroup(['1/1/3/2'], (new Block())->img(0.5, 0.8, $minImgWidth1)->bigTitle()),
            new BlockGroup(['1/2/3/2'], (new Block())->text(0, 280)),
            new BlockGroup(['', '', '', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('t 2 2 2', ...[
            // http://localhost:8081/?/blog/2017/06/30/amp
            // http://localhost:8081/?/blog/2021/09/28/new_domain
            // http://localhost:8081/?/blog/2019/11/16/move_su_from_rucenter
            // http://localhost:8081/?/blog/2019/11/04/rucenter_services
            // http://localhost:8081/?/blog/2017/10/29/viewport_in_ie
            // http://localhost:8081/?/blog/2017/01/22/nevzorov_smokes
            new BlockGroup(['1/1/3/2'], (new Block())->img(0, 1, 90, 400)->imgClass('thumb')->bigTitle()->text(0, 250)),
            new BlockGroup(['1/2', '1/3', '1/4'], (new Block())->text(0, 200)),
            new BlockGroup(['2/2', '2/3', '2/4'], (new Block())),
        ]);
        $r->addGroup('t+1 2 2 2', ...[
            // http://localhost:8081/?/blog/2013/08/03/on_it
            // http://localhost:8081/?/blog/2006/03/25/Tvor4estvovslog
            new BlockGroup(['1/1'], (new Block())->img(0, 1, 90, 400)->imgClass('thumb')->bigTitle()),
            new BlockGroup(['', '', '', '', '', '', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('i 1 2', ...[
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.6, $minImgWidth2)->bigTitle()->text(0, 150)),
            new BlockGroup(['1/3/3/4'], (new Block())->text(0, 300)),
            new BlockGroup(['1/4', '2/4'], (new Block())),
        ]);
        $r->addGroup('1+(1 1) ir', ...[
            // http://localhost:8081/?/blog/2007/07/20/Usaitadenjrojde
            new BlockGroup(['1/3/3/5'], (new Block())->img(1, 2, 180)->imgClass('right')->bigTitle()->text()),
            new BlockGroup(['1/1/2/3'], (new Block())->text(0, 280)),
            new BlockGroup(['', ''], (new Block())->text(0, 150)),
        ]);
        $r->addGroup('2 3 ir', ...[
            // http://localhost:8081/?/blog/2012/01/14/hyphens
            // http://localhost:8081/?/blog/2009/07/20/written_ru_4
            new BlockGroup(['1/3/7/5'], (new Block())->img(1, 2, 180)->imgClass('right')->bigTitle()->text()),
            new BlockGroup(['1/2/4/3', '4/2/7/3'], (new Block())->text(0, 280)),
            new BlockGroup(['1/1/3/2', '3/1/5/2', '5/1/7/2'], (new Block())->text(0, 150)),
        ]);
        $r->addGroup('i_wider+(1 1) 3 3', ...[
            // http://localhost:8081/?/blog/2011/09/20/28_power
            // http://localhost:8081/?/blog/2021/09/12/smart_vote
            // http://localhost:8081/?/blog/2017/03/05/Who_are_the_judges
            // http://localhost:8081/?/blog/2012/09/28/why_physics
            new BlockGroup(['1/1/3/3'], (new Block())->img(0, 0.6, $minImgWidth2)->bigTitle()->text(0, 0, 500)),
            new BlockGroup(['', '', '', ''], (new Block())->text(0, 150)),
            new BlockGroup(['', '', '', ''], (new Block())),
        ]);
        $r->addGroup('i+(1 1) 3 3', ...[
            // http://localhost:8081/?/blog/2019/01/16/another_citizenship
            // http://localhost:8081/?/blog/2016/09/30/being_atheist
            new BlockGroup(['1/1/3/3'], (new Block())->img(0.6, 0.8, $minImgWidth2)->bigTitle()),
            new BlockGroup(['', '', '', ''], (new Block())->text(0, 280)),
            new BlockGroup(['', '', '', ''], (new Block())),
        ]);
        $r->addGroup('i + 1 + 1 + 1', ...[
            new BlockGroup(['1/1/3/2'], (new Block())->img(0, 0.8, $minImgWidth1)->bigTitle()),
            new BlockGroup(['auto', 'auto', 'auto'], (new Block())->text(0, 280)),
        ]);
        $r->addGroup('t 1 1 1', ...[
            // http://localhost:8081/?/blog/2012/02/01/uncontrollable_soft
            new BlockGroup(['1/1/3/2'], Block::thumbnail()->bigTitle()->text(0, 150)),
            new BlockGroup(['auto', 'auto', 'auto'], (new Block())->text(0, 280)),
        ]);
        $r->addGroup('i 1 1', ...[
            new BlockGroup(['1/1'], (new Block())->img(0, 0.8, $minImgWidth1)->bigTitle()),
            new BlockGroup(['1/3/2/5'], (new Block())->text()->bigTitle()),
            new BlockGroup(['auto'], (new Block())->text()),
        ]);
        $r->addGroup('i 1', ...[
            // http://localhost:8081/?/blog/2011/08/24/no_tree
            new BlockGroup(['1/1'], Block::img1column()),
            new BlockGroup(['1/2/2/4'], (new Block())->text()->bigTitle()),
        ]);
        $r->addGroup('ir (2)', ...[
            // http://localhost:8081/?/blog/2016/02/02/omg
            // http://localhost:8081/?/blog/2009/10/06/Uran
            new BlockGroup(['1/1/2/3'], (new Block())->img(0.4, 0.8, 180)->imgClass('right')->bigTitle()->text()),
        ]);
        $r->addGroup('ir', ...[
            new BlockGroup(['1/1/2/3'], (new Block())->img(1, 2, 180, 250)->imgClass('right')->bigTitle()->text()),
        ]);
        $r->addGroup('ir (3)', ...[
            // http://localhost:8081/?/blog/2008/08/03/ps_warning
            new BlockGroup(['1/1/2/4'], (new Block())->img(0.2, 0.4, 180)->imgClass('right')->bigTitle()->text()),
        ]);
        $r->addGroup('ir (4)', ...[
            new BlockGroup(['1/1/2/3'], (new Block())->img(1, 4, 180, 560)->imgClass('right2')->bigTitle()->text()),
        ]);
        $r->addGroup('t 2 2 1', ...[
            // http://localhost:8081/?/blog/2006/05/17/Vsemirniidenjin
            new BlockGroup(['1/1/3/2'], Block::thumbnail()->bigTitle()),
            new BlockGroup(['1/2/3/2'], (new Block())->text(0, 300)),
            new BlockGroup(['', '', '', ''], (new Block())->text(0, 80)),
        ]);
        $r->addGroup('t', ...[
            // http://localhost:8081/?/blog/2020/05/05/window
            new BlockGroup(['1/1/2/4'], Block::thumbnail()->bigTitle()->text()),
        ]);

        // 0 images
        $r->addGroup('2 2 2 2', ...[
            // http://localhost:8081/?/blog/2016/04/05/latex_success
            new BlockGroup(['', '', '', '', '', '', '', ''], (new Block())),
        ]);
        $r->addGroup('1 1 1', ...[
            // http://localhost:8081/?/blog/2011/02/23/Headphones
            new BlockGroup(['1/1/2/3'], (new Block())->bigTitle(40)->text()),
            new BlockGroup(['auto', 'auto'], (new Block())->bigTitle(25)),
        ]);
        $r->addGroup('1 1', ...[
            // http://localhost:8081/?/blog/2016/07/31/sky
            new BlockGroup(['1/1/2/3', '1/3/2/5'], (new Block())->bigTitle(40)),
        ]);
        $r->addGroup('1', ...[
            // http://localhost:8081/?/blog/2013/05/29/Android
            // http://localhost:8081/?/blog/2011/07/20/Sultanov
            new BlockGroup(['1/1/2/4'], (new Block())->bigTitle()->text()),
        ]);

        return $r;
    }
}
