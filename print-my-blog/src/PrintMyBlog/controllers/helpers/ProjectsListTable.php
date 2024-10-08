<?php

namespace PrintMyBlog\controllers\helpers;

use PrintMyBlog\controllers\Admin;
use PrintMyBlog\orm\entities\Project;
use PrintMyBlog\orm\managers\ProjectManager;
use PrintMyBlog\system\Context;
use PrintMyBlog\system\CustomPostTypes;
use WP_List_Table;
use WP_Post;
use WP_Query;
// phpcs:disable PSR12.Files.FileHeader.IncorrectOrder
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
/**
 * Class ProjectsListTable
 *
 * Description
 *
 * @package        Print My Blog
 * @author         Mike Nelson
 * @since          $VID:$
 *
 */
class ProjectsListTable extends WP_List_Table {
    /**
     * @var mixed|object
     */
    protected $project_manager;

    /**
     * ProjectsListTable constructor.
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => 'project',
            'plural'   => 'projects',
        ) );
    }

    /** * Prepare the items for the table to process
     * * @return Void
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $per_page = $this->get_items_per_page( 'records_per_page', 10 );
        $current_page = $this->get_pagenum();
        $total_items = self::recordCount();
        $data = $this->get_records( $per_page, $current_page );
        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ] );
        $this->items = $data;
    }

    /**
     * Retrieve records data from the database
     * @param int $per_page
     * @param int $page_number
     * @return mixed
     */
    public function get_records( $per_page = 10, $page_number = 1 ) {
        return $this->getProjectManager()->getAll( $this->wp_query( $per_page, $page_number ) );
    }

    /**
     * @param int $per_page
     * @param int $page_number
     * @return WP_Query
     */
    protected function wp_query( $per_page = null, $page_number = null ) {
        $params = [];
        if ( isset( $per_page ) ) {
            $params['posts_per_page'] = $per_page;
        }
        if ( isset( $page_number ) ) {
            $params['paged'] = $page_number;
        }
        return new WP_Query($params);
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     * * @return array
     */
    public function get_columns() {
        $columns = [
            'cb'     => '<input type="checkbox" />',
            'title'  => __( 'Title', 'print-my-blog' ),
            'format' => __( 'Format', 'print-my-blog' ),
            'date'   => __( 'Date', 'print-my-blog' ),
        ];
        return $columns;
    }

    /**
     * There are no hidden columns.
     * @return array
     */
    public function get_hidden_columns() {
        // Setup Hidden columns and return them
        return array();
    }

    /**
     * Columns to make sortable.
     * * @return array
     */
    public function get_sortable_columns() {
        $sortable_columns = array();
        return $sortable_columns;
    }

    /**
     * @return array
     */
    protected function get_bulk_actions() {
        return [
            'delete' => __( 'Delete' ),
        ];
    }

    /**
     * Render the bulk edit checkbox
     * @param Project $project
     * @return string
     */
    public function column_cb( $project ) {
        return sprintf( '<input type="checkbox" name="ID[]" value="%s" />', $project->getWpPost()->ID );
    }

    /**
     * Render the bulk edit checkbox
     * @param Project $project
     */
    public function column_title( Project $project ) {
        $post = $project->getWpPost();
        $title = ( $post->post_title ? $post->post_title : __( 'Untitled', 'print-my-blog' ) );
        $steps = $project->getProgress()->getSteps();
        $progress = $project->getProgress()->getStepProgress();
        $next_step = $project->getProgress()->getNextStep();
        $steps_to_urls = $project->getProgress()->stepsToUrls();
        printf( '<strong><a href="%s" class="btn btn-primary"/>%s</a></strong>', esc_url_raw( $steps_to_urls[$next_step] ), esc_html( $title ) );
        ?><div class="pmb-row-actions row-actions">
        <?php 
        foreach ( $steps as $slug => $display_text ) {
            $completed = ( $progress[$slug] ? true : false );
            $next = ( $next_step === $slug ? true : false );
            $accessible = $completed || $next;
            ?>
            <span class="pmb-step
            <?php 
            echo esc_attr( ( $completed ? 'pmb-completed' : 'pmb-incomplete' ) );
            ?>
            <?php 
            echo esc_attr( ( $next ? 'pmb-next-step' : '' ) );
            ?>
            <?php 
            echo esc_attr( ( $accessible ? 'pmb-accessible-step' : 'pmb-inaccessible-step' ) );
            ?>
            ">
            <?php 
            if ( $completed || $next ) {
                ?>
                <a href="<?php 
                echo esc_attr( $steps_to_urls[$slug] );
                ?>">
                <?php 
            }
            echo esc_html( $display_text );
            if ( $completed || $next ) {
                ?>
                    </a>
                    <?php 
            }
            ?>
            </span>
            <?php 
        }
        ?>
<span class="pmb-dont-break-phrase">| &nbsp; 
        <?php 
        esc_html_e( 'Duplicate', 'print-my-blog' );
        echo '&nbsp;';
        // We want to show HTML here.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo pmb_hover_tip( __( 'Feature included in Professional and Business licenses', 'print-my-blog' ) );
        ?>
        </span>
        <?php 
    }

    /**
     * @param Project $project
     */
    public function column_format( Project $project ) {
        $names = [];
        foreach ( $project->getFormatsSelected() as $format ) {
            $names[] = $format->title();
        }
        return implode( ', ', $names );
    }

    /**
     * @param Project $project
     * @return string
     */
    public function column_date( Project $project ) {
        return __( 'Started', 'print-my-blog' ) . '<br>' . sprintf( 
            // translators: 1: Post date, 2: Post time.
            __( '%1$s at %2$s', 'print-my-blog' ),
            /* translators: Post date format. See https://www.php.net/date */
            get_the_time( __( 'Y/m/d' ), $project->getWpPost() ),
            /* translators: Post time format. See https://www.php.net/date */
            get_the_time( __( 'g:i a' ), $project->getWpPost() )
         );
    }

    /**
     * Text displayed when no record data is available
     */
    public function no_items() {
        esc_html_e( 'Click "Add New Project" 👆 To Make Your First Project!', 'print-my-blog' );
    }

    /**
     * Returns the count of records in the database.
     * * @return null|string
     */
    public function recordCount() {
        return $this->getProjectManager()->count( $this->wp_query() );
    }

    /**
     * @return ProjectManager
     */
    protected function getProjectManager() {
        if ( $this->project_manager === null ) {
            $this->project_manager = Context::instance()->reuse( 'PrintMyBlog\\orm\\managers\\ProjectManager' );
        }
        return $this->project_manager;
    }

}
